<?php

namespace App\Exports;

use App\Support\ClassListColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One class, two sheets: the empty "Learners" sheet and the "Instructions" sheet,
 * for either a single class or the whole school.
 */
class LearnerImportTemplateSheet implements FromArray, WithStyles, WithTitle
{
    private const CLASS_COLUMNS = [
        'assessment_number', 'full_name', 'admission_number', 'admission_date', 'citizenship', 'gender',
        'kemis_code', 'date_of_birth', 'birth_certificate_number', 'contact_person', 'lunch',
    ];

    private const SCHOOL_COLUMNS = [
        'branch', 'class', 'assessment_number', 'full_name', 'admission_number', 'admission_date', 'citizenship',
        'gender', 'kemis_code', 'date_of_birth', 'birth_certificate_number', 'contact_person', 'lunch',
        'transport_route', 'bus', 'status',
    ];

    /** key => [required?, what to enter] */
    private const HELP = [
        'branch' => ['Yes*', 'Juja Road, Kitisuru or South C. *Not needed if you manage only one branch.'],
        'class' => ['Yes', 'The class the learner is in now, for example Grade 3 Blue. Must match a class set up in the system.'],
        'assessment_number' => ['No', 'Leave blank if the learner has none yet. Must be unique.'],
        'full_name' => ['Yes', 'Full name as per the birth certificate.'],
        'admission_number' => ['Yes', 'Must be unique across the whole school.'],
        'admission_date' => ['No', 'The day the learner joined the school, day first, for example 12/01/2022. Leave blank if unknown.'],
        'citizenship' => ['No', 'Citizen or Non citizen. Blank means Citizen.'],
        'gender' => ['Yes', 'Male or Female.'],
        'kemis_code' => ['No', 'Leave blank if not issued yet. Must be unique.'],
        'date_of_birth' => ['Yes', 'Day first, for example 25/03/2018.'],
        'birth_certificate_number' => ['No', ''],
        'contact_person' => ['No', ''],
        'lunch' => ['No', 'Yes or No. Blank means No.'],
        'transport_route' => ['No', 'Must match a route already added under Transport.'],
        'bus' => ['No', 'Number plate of a bus already added under Transport, in the same branch.'],
        'status' => ['No', 'Active, Inactive or Transferred. Blank means Active.'],
    ];

    public function __construct(private bool $schoolWide, private bool $instructions)
    {
    }

    private function keys(): array
    {
        return $this->schoolWide ? self::SCHOOL_COLUMNS : self::CLASS_COLUMNS;
    }

    public function array(): array
    {
        if (! $this->instructions) {
            return [ClassListColumns::headings($this->keys())];
        }

        $rows = [['Column', 'Required', 'What to enter']];
        foreach ($this->keys() as $key) {
            $rows[] = [ClassListColumns::headings([$key])[0], self::HELP[$key][0], self::HELP[$key][1]];
        }

        $rows[] = [];
        $rows[] = ['Notes'];

        $notes = $this->schoolWide
            ? ['Learners are added to the current academic year, in the class named on each row.']
            : ['Every learner is added to the class you import from, as Active, in the current academic year.'];

        $notes[] = 'Existing learners are never changed. A row with an admission, assessment or Kemis number already in the system is reported as an error.';
        $notes[] = 'If any row has an error, nothing is imported. Fix the rows listed and upload the file again.';
        $notes[] = 'Keep the column headings as they are. A file exported from this system can be used as it is.';

        foreach ($notes as $note) {
            $rows[] = [$note];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        if ($this->instructions) {
            $sheet->getColumnDimension('A')->setWidth(26);
            $sheet->getColumnDimension('B')->setWidth(12);
            $sheet->getColumnDimension('C')->setWidth(95);

            return [1 => ['font' => ['bold' => true]], count($this->keys()) + 3 => ['font' => ['bold' => true]]];
        }

        $count = count($this->keys());
        $lastColumn = Coordinate::stringFromColumnIndex($count);

        // Text format keeps typed numbers and dates exactly as entered.
        $sheet->getStyle("A2:{$lastColumn}2001")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        for ($i = 1; $i <= $count; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setWidth(22);
        }
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return $this->instructions ? 'Instructions' : 'Learners';
    }
}
