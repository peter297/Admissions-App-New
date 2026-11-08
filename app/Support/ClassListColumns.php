<?php

namespace App\Support;

use App\Models\Enrollment;
use Illuminate\Support\Collection;

/**
 * Single source of truth for every column a class export can contain.
 * Add a column here and it appears in the dialog, the Excel file and the PDF.
 */
class ClassListColumns
{
    /** @return array<string, array{label: string, option: string, value: callable}> */
    public static function all(): array
    {
        return [
            'row_number' => [
                'label' => 'No.', 'option' => 'Row numbers (No.)',
                'value' => fn (Enrollment $e, int $i) => $i + 1,
            ],
            'assessment_number' => [
                'label' => 'Assessment No.', 'option' => 'Assessment No.',
                'value' => fn (Enrollment $e) => $e->learner->assessment_number,
            ],
            'full_name' => [
                'label' => 'Name', 'option' => 'Name',
                'value' => fn (Enrollment $e) => $e->learner->full_name,
            ],
            'admission_number' => [
                'label' => 'Admission No.', 'option' => 'Admission No.',
                'value' => fn (Enrollment $e) => $e->learner->admission_number,
            ],
            'class' => [
                'label' => 'Class', 'option' => 'Class',
                'value' => fn (Enrollment $e) => $e->classRoom->name,
            ],
            'citizenship' => [
                'label' => 'Citizenship', 'option' => 'Citizenship',
                'value' => fn (Enrollment $e) => $e->learner->nationality === 'citizen' ? 'Citizen' : 'Non citizen',
            ],
            'gender' => [
                'label' => 'Gender', 'option' => 'Gender',
                'value' => fn (Enrollment $e) => ucfirst($e->learner->gender),
            ],
            'kemis_code' => [
                'label' => 'Kemis No.', 'option' => 'Kemis No.',
                'value' => fn (Enrollment $e) => $e->learner->kemis_code,
            ],
            'date_of_birth' => [
                'label' => 'Date of Birth', 'option' => 'Date of birth',
                'value' => fn (Enrollment $e) => $e->learner->date_of_birth?->format('d/m/Y'),
            ],
            'birth_certificate_number' => [
                'label' => 'Birth Cert. Entry No.', 'option' => 'Birth certificate entry no.',
                'value' => fn (Enrollment $e) => $e->learner->birth_certificate_number,
            ],
            'contact_person' => [
                'label' => 'Contact Person', 'option' => 'Contact person',
                'value' => fn (Enrollment $e) => $e->learner->contact_person,
            ],
            'lunch' => [
                'label' => 'Lunch', 'option' => 'Lunch',
                'value' => fn (Enrollment $e) => $e->takes_lunch ? 'Yes' : 'No',
            ],
            'transport_route' => [
                'label' => 'Transport Route', 'option' => 'Transport route',
                'value' => fn (Enrollment $e) => $e->transportRoute?->name,
            ],
            'bus' => [
                'label' => 'Bus', 'option' => 'Bus',
                'value' => fn (Enrollment $e) => $e->bus?->number_plate,
            ],
            'activities' => [
                'label' => 'Co-curricular', 'option' => 'Co-curricular activities',
                'value' => fn (Enrollment $e) => $e->activities->pluck('name')->implode(', '),
            ],
            'status' => [
                'label' => 'Status', 'option' => 'Status',
                'value' => fn (Enrollment $e) => ucfirst($e->status),
            ],

            'admission_date' => [
                'label' => 'Admission Date', 'option' => 'Admission date',
                'value' => fn (Enrollment $e) => $e->learner->date_of_admission?->format('d/m/Y'),
            ],

            'branch' => [
                'label' => 'Branch', 'option' => 'Branch',
                'value' => fn (Enrollment $e) => $e->branch->name,
            ],
        ];
    }

    /** What the React dialog needs: key and label. */
    public static function options(): array
    {
        return collect(self::all())
            ->map(fn ($c, $key) => ['key' => $key, 'label' => $c['option']])
            ->values()
            ->all();
    }

    /** Keep the chosen keys, but always in the canonical order above. */
    public static function normalise(array $keys): array
    {
        return array_values(array_intersect(array_keys(self::all()), $keys));
    }

    public static function headings(array $keys): array
    {
        $all = self::all();

        return array_map(fn ($k) => $all[$k]['label'], $keys);
    }

    /** Every cell is returned as a string so Excel never reformats long numbers. */
    public static function rows(Collection $enrollments, array $keys): array
    {
        $all = self::all();

        return $enrollments->values()->map(function (Enrollment $e, int $i) use ($all, $keys) {
            return array_map(function ($k) use ($all, $e, $i) {
                $v = $all[$k]['value']($e, $i);

                return $v === null ? '' : (string) $v;
            }, $keys);
        })->all();
    }
}
