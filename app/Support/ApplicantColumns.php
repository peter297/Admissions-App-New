<?php

namespace App\Support;

use App\Models\Applicant;
use Illuminate\Support\Collection;

/** Same idea as ClassListColumns, for the admissions report. */
class ApplicantColumns
{
    public static function all(): array
    {
        return [
            'row_number' => ['label' => 'No.', 'option' => 'Row numbers (No.)',
                'value' => fn (Applicant $a, int $i) => $i + 1],
            'full_name' => ['label' => 'Name', 'option' => 'Name',
                'value' => fn (Applicant $a) => $a->full_name],
            'grade' => ['label' => 'Grade Applied', 'option' => 'Grade applied for',
                'value' => fn (Applicant $a) => $a->gradeLevel->name],
            'branch' => ['label' => 'Branch', 'option' => 'Branch',
                'value' => fn (Applicant $a) => $a->branch->name],
            'assessment_number' => ['label' => 'Assessment No.', 'option' => 'Assessment No.',
                'value' => fn (Applicant $a) => $a->assessment_number],
            'date_of_birth' => ['label' => 'Date of Birth', 'option' => 'Date of birth',
                'value' => fn (Applicant $a) => $a->date_of_birth?->format('d/m/Y')],
            'gender' => ['label' => 'Gender', 'option' => 'Gender',
                'value' => fn (Applicant $a) => ucfirst((string) $a->gender)],
            'parent_name' => ['label' => 'Parent / Guardian', 'option' => 'Parent / guardian name',
                'value' => fn (Applicant $a) => $a->parent_name],
            'parent_type' => ['label' => 'Relationship', 'option' => 'Relationship (mother / father)',
                'value' => fn (Applicant $a) => ucfirst($a->parent_type)],
            'parent_phone' => ['label' => 'Phone', 'option' => 'Phone number',
                'value' => fn (Applicant $a) => $a->parent_phone],
            'mpesa_code' => ['label' => 'M-Pesa Code', 'option' => 'M-Pesa code',
                'value' => fn (Applicant $a) => $a->mpesa_code],
            'result' => ['label' => 'Result', 'option' => 'Result',
                'value' => fn (Applicant $a) => ucfirst($a->result)],
            'admitted' => ['label' => 'Admitted', 'option' => 'Admitted (placed in a class)',
                'value' => fn (Applicant $a) => $a->promoted_at ? 'Yes' : 'No'],
            'notes' => ['label' => 'Notes', 'option' => 'Notes',
                'value' => fn (Applicant $a) => $a->notes],
            'academic_year' => ['label' => 'Year', 'option' => 'Academic year',
                'value' => fn (Applicant $a) => $a->academicYear->name],
        ];
    }

    public static function options(): array
    {
        return collect(self::all())
            ->map(fn ($c, $key) => ['key' => $key, 'label' => $c['option']])
            ->values()->all();
    }

    public static function normalise(array $keys): array
    {
        return array_values(array_intersect(array_keys(self::all()), $keys));
    }

    public static function headings(array $keys): array
    {
        $all = self::all();

        return array_map(fn ($k) => $all[$k]['label'], $keys);
    }

    /** Strings only, so Excel keeps phone numbers and M-Pesa codes exactly as typed. */
    public static function rows(Collection $applicants, array $keys): array
    {
        $all = self::all();

        return $applicants->values()->map(fn (Applicant $a, int $i) => array_map(function ($k) use ($all, $a, $i) {
            $v = $all[$k]['value']($a, $i);

            return $v === null ? '' : (string) $v;
        }, $keys))->all();
    }
}
