<?php

namespace App\Support;

use App\Models\Learner;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reads an uploaded learner file and checks every row. It never writes to the
 * database: the controller decides what to do with the result.
 *
 * Without $lookups it reads a single-class file. With $lookups it also reads the
 * Branch, Class, Route, Bus and Status columns, for a whole-school file.
 */
class ClassListImporter
{
    public const MAX_ROWS = 1000;

    public const REQUIRED = ['full_name', 'admission_number', 'gender', 'date_of_birth'];

    /** The columns that belong to the learner record itself. */
    public const LEARNER_FIELDS = [
        'full_name', 'assessment_number', 'admission_number', 'admission_date', 'gender',
        'date_of_birth', 'birth_certificate_number', 'nationality', 'kemis_code', 'contact_person',
    ];

    private const LABELS = [
        'full_name' => 'Name',
        'admission_number' => 'Admission No.',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of Birth',
    ];

    /** Headings are compared after lowercasing and dropping spaces and punctuation. */
    private const ALIASES = [
        'full_name' => ['name', 'fullname', 'fullnames', 'learnername'],
        'admission_number' => ['admissionno', 'admissionnumber', 'admno'],
        'admission_date' => ['admissiondate', 'dateofadmission', 'dateadmitted', 'admittedon'],
        'assessment_number' => ['assessmentno', 'assessmentnumber'],
        'nationality' => ['citizenship', 'nationality'],
        'gender' => ['gender', 'sex'],
        'kemis_code' => ['kemisno', 'kemiscode', 'kemis', 'upi'],
        'date_of_birth' => ['dateofbirth', 'dob'],
        'birth_certificate_number' => ['birthcertentryno', 'birthcertificateentrynumber', 'birthcertificatenumber', 'birthcertno', 'birthcertificateno'],
        'contact_person' => ['contactperson', 'parentguardian', 'guardian'],
        'lunch' => ['lunch', 'schoollunch'],
        // whole-school columns
        'branch' => ['branch', 'campus'],
        'class' => ['class', 'classname', 'currentclass'],
        'grade' => ['grade', 'gradelevel'],
        'stream' => ['stream'],
        'status' => ['status', 'learnerstatus'],
        'route' => ['transportroute', 'route'],
        'bus' => ['bus', 'busplate', 'busnumberplate'],
    ];

    /** @return array{fatal: ?string, records: array, errors: array} */
    public function read(UploadedFile $file, ?LearnerImportLookups $lookups = null, int $maxRows = self::MAX_ROWS): array
    {
        $reader = new class implements ToArray {
            public function array(array $array): void
            {
            }
        };

        $rows = Excel::toArray($reader, $file)[0] ?? [];

        [$headerRow, $map] = $this->findHeader($rows);

        if ($headerRow === null) {
            return $this->fatal('I could not find the column headings. The file needs at least the "Name" and "Admission No." columns. Download the template to see the layout.');
        }

        $missing = array_diff(self::REQUIRED, array_keys($map));
        if ($missing) {
            $labels = array_map(fn ($k) => self::LABELS[$k], $missing);

            return $this->fatal('These required columns are missing: '.implode(', ', $labels).'.');
        }

        if ($lookups && ! isset($map['class']) && ! (isset($map['grade']) && isset($map['stream']))) {
            return $this->fatal('The Class column is missing. A whole-school file needs a Class column (for example "Grade 3 Blue"), or separate Grade and Stream columns.');
        }

        $parsed = [];
        for ($i = $headerRow + 1; $i < count($rows); $i++) {
            if ($this->isBlank($rows[$i])) {
                continue;
            }
            $parsed[] = $this->parseRow($rows[$i], $map, $i + 1, $lookups); // $i + 1 = the row number Excel shows
        }

        if (count($parsed) === 0) {
            return $this->fatal('The file has no learners under the column headings.');
        }
        if (count($parsed) > $maxRows) {
            return $this->fatal("The file has more than {$maxRows} learners. Please split it into smaller files.");
        }

        $parsed = $this->checkDuplicates($parsed);

        $errors = [];
        $records = [];
        foreach ($parsed as $p) {
            $records[] = $p['data'];
            if ($p['errors']) {
                $name = $p['data']['full_name'] ?: 'no name';
                $errors[] = "Row {$p['row']} ({$name}): ".implode('; ', $p['errors']);
            }
        }

        return ['fatal' => null, 'records' => $records, 'errors' => $errors];
    }

    /* ---------------------------------------------------------------
     |  Header detection
     * ------------------------------------------------------------- */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $map = [];
            foreach ($row as $col => $cell) {
                $norm = preg_replace('/[^a-z0-9]/', '', strtolower((string) $cell));
                foreach (self::ALIASES as $key => $aliases) {
                    if (! isset($map[$key]) && in_array($norm, $aliases, true)) {
                        $map[$key] = $col;
                        break; // one heading belongs to one field
                    }
                }
            }
            // A heading row must at least contain a name and an admission number column.
            if (isset($map['full_name'], $map['admission_number'])) {
                return [$index, $map];
            }
        }

        return [null, []];
    }

    /* ---------------------------------------------------------------
     |  One row
     * ------------------------------------------------------------- */
    private function parseRow(array $row, array $map, int $rowNumber, ?LearnerImportLookups $lookups): array
    {
        $cell = fn (string $key) => isset($map[$key]) ? ($row[$map[$key]] ?? null) : null;
        $errors = [];

        $name = $this->text($cell('full_name'));
        $admission = $this->text($cell('admission_number'));
        $assessment = $this->text($cell('assessment_number'));
        $kemis = $this->text($cell('kemis_code'));
        $birthCert = $this->text($cell('birth_certificate_number'));
        $contact = $this->text($cell('contact_person'));

        if (! $name) $errors[] = 'Name is missing';
        if (! $admission) $errors[] = 'Admission No. is missing';

        foreach ([['Name', $name, 255], ['Admission No.', $admission, 50], ['Assessment No.', $assessment, 50],
                  ['Kemis No.', $kemis, 50], ['Birth Cert. Entry No.', $birthCert, 50], ['Contact Person', $contact, 255]] as [$label, $value, $max]) {
            if ($value !== null && mb_strlen($value) > $max) {
                $errors[] = "{$label} is too long";
            }
        }

        $gender = $this->gender($cell('gender'));
        if (! $gender) $errors[] = 'Gender must be Male or Female';

        $nationality = $this->nationality($cell('nationality'));
        if (! $nationality) $errors[] = 'Citizenship must be Citizen or Non citizen';

        $lunch = $this->lunch($cell('lunch'));
        if ($lunch === null) $errors[] = 'Lunch must be Yes or No';

        $dob = $this->parseDate($cell('date_of_birth'));
        if (! $dob) {
            $errors[] = 'Date of Birth is missing or not a valid date (use dd/mm/yyyy)';
        } elseif ($dob >= now()->toDateString() || $dob < now()->subYears(25)->toDateString()) {
            $errors[] = 'Date of Birth does not look right';
        }

        // Admission date is optional. Old records often do not have one.
        $admissionDate = null;
        $rawAdmissionDate = $cell('admission_date');
        if ($rawAdmissionDate !== null && trim((string) $rawAdmissionDate) !== '') {
            $admissionDate = $this->parseDate($rawAdmissionDate);
            if (! $admissionDate) {
                $errors[] = 'Admission date is not a valid date (use dd/mm/yyyy)';
            } elseif ($admissionDate > now()->toDateString()) {
                $errors[] = 'Admission date is in the future';
            } elseif ($dob && $admissionDate < $dob) {
                $errors[] = 'Admission date is before the date of birth';
            }
        }

        $data = [
            'full_name' => $name,
            'assessment_number' => $assessment,
            'admission_number' => $admission,
            'admission_date' => $admissionDate,
            'gender' => $gender,
            'date_of_birth' => $dob,
            'birth_certificate_number' => $birthCert,
            'nationality' => $nationality,
            'kemis_code' => $kemis,
            'contact_person' => $contact,
            'takes_lunch' => (bool) $lunch,
        ];

        // Whole-school files also say where each learner belongs.
        if ($lookups) {
            [$ids, $problems] = $lookups->resolve([
                'branch' => $this->text($cell('branch')),
                'class' => $this->text($cell('class')),
                'grade' => $this->text($cell('grade')),
                'stream' => $this->text($cell('stream')),
                'status' => $this->text($cell('status')),
                'route' => $this->text($cell('route')),
                'bus' => $this->text($cell('bus')),
            ]);
            $data += $ids;
            $errors = array_merge($errors, $problems);
        }

        return ['row' => $rowNumber, 'errors' => $errors, 'data' => $data];
    }

    /* ---------------------------------------------------------------
     |  Duplicates: inside the file, and against learners already saved
     * ------------------------------------------------------------- */
    private function checkDuplicates(array $parsed): array
    {
        $fields = [
            'admission_number' => 'Admission No.',
            'assessment_number' => 'Assessment No.',
            'kemis_code' => 'Kemis No.',
        ];

        foreach ($fields as $field => $label) {
            // 1. repeated inside the file
            $seen = [];
            foreach ($parsed as $i => $p) {
                $value = $p['data'][$field];
                if ($value === null) continue;
                $key = mb_strtolower($value);
                if (isset($seen[$key])) {
                    $parsed[$i]['errors'][] = "{$label} {$value} is repeated (first on row {$seen[$key]})";
                } else {
                    $seen[$key] = $p['row'];
                }
            }

            // 2. already in the system (soft-deleted learners still hold their numbers)
            $values = array_values(array_filter(array_column(array_column($parsed, 'data'), $field)));
            if (! $values) continue;

            $existing = [];
            foreach (array_chunk($values, 1000) as $chunk) {
                foreach (Learner::withTrashed()->whereIn($field, $chunk)->pluck($field) as $found) {
                    $existing[mb_strtolower($found)] = true;
                }
            }

            foreach ($parsed as $i => $p) {
                $value = $p['data'][$field];
                if ($value !== null && isset($existing[mb_strtolower($value)])) {
                    $parsed[$i]['errors'][] = "{$label} {$value} already exists in the system";
                }
            }
        }

        return $parsed;
    }

    /* ---------------------------------------------------------------
     |  Value helpers
     * ------------------------------------------------------------- */
    private function text(mixed $v): ?string
    {
        if ($v === null) return null;
        if (is_float($v) && floor($v) == $v) $v = (int) $v; // 12345.0 -> "12345"
        $s = trim((string) preg_replace('/\s+/u', ' ', (string) $v));

        return $s === '' ? null : $s;
    }

    private function gender(mixed $v): ?string
    {
        return match (strtolower((string) $this->text($v))) {
            'm', 'male', 'boy' => 'male',
            'f', 'female', 'girl' => 'female',
            default => null,
        };
    }

    private function nationality(mixed $v): ?string
    {
        $s = preg_replace('/[^a-z]/', '', strtolower((string) $this->text($v)));

        return match ($s) {
            '', 'citizen', 'kenyan' => 'citizen',
            'noncitizen', 'foreigner', 'foreign', 'nonkenyan' => 'non_citizen',
            default => null,
        };
    }

    private function lunch(mixed $v): ?bool
    {
        return match (strtolower((string) $this->text($v))) {
            '', 'no', 'n', 'false', '0' => false,
            'yes', 'y', 'true', '1' => true,
            default => null,
        };
    }

    /** Day first (Kenyan style). Ambiguous month-first dates are rejected, not guessed. */
    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        try {
            if (is_numeric($value)) {
                $n = (float) $value;
                if ($n < 10000 || $n > 80000) return null; // not an Excel date serial
                return ExcelDate::excelToDateTimeObject($n)->format('Y-m-d');
            }

            $v = trim((string) $value);

            if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $v, $m)) {
                return Carbon::createSafe((int) $m[3], (int) $m[2], (int) $m[1])->format('Y-m-d');
            }
            if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $v, $m)) {
                return Carbon::createSafe((int) $m[1], (int) $m[2], (int) $m[3])->format('Y-m-d');
            }

            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function isBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') return false;
        }

        return true;
    }

    private function fatal(string $message): array
    {
        return ['fatal' => $message, 'records' => [], 'errors' => []];
    }
}
