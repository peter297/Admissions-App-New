<?php

namespace App\Exports;

use App\Support\ClassListColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One class, two sheets: the empty "Learners" sheet and the "Instructions" sheet.
 */
class LearnerImportTemplateSheet implements FromArray, WithStyles, WithTitle
{
    /** Same keys the importer reads, so headings always match the export. */
    private const COLUMNS = [
        'assessment_number', 'full_name', 'admission_number', 'citizenship', 'gender',
        'kemis_code', 'date_of_birth', 'birth_certificate_number', 'contact_person', 'lunch',
    ];

    public function __construct(private bool $instructions)
    {
    }

    public function array(): array
    {
        if (! $this->instructions) {
            return [ClassListColumns::headings(self::COLUMNS)];
        }

        return [
            ['Column', 'Required', 'What to enter'],
            ['Assessment No.', 'No', 'Leave blank if the learner has none yet. Must be unique.'],
            ['Name', 'Yes', 'Full name as per the birth certificate.'],
            ['Admission No.', 'Yes', 'Must be unique across the whole school.'],
            ['Citizenship', 'No', 'Citizen or Non citizen. Blank means Citizen.'],
            ['Gender', 'Yes', 'Male or Female.'],
            ['Kemis No.', 'No', 'Leave blank if not issued yet. Must be unique.'],
            ['Date of Birth', 'Yes', 'Day first, for example 25/03/2018.'],
            ['Birth Cert. Entry No.', 'No', ''],
            ['Contact Person', 'No', ''],
            ['Lunch', 'No', 'Yes or No. Blank means No.'],
            [],
            ['Notes'],
            ['Every learner is added to the class you import from, as Active, in the current academic year.'],
            ['Existing learners are never changed. A row with an admission, assessment or Kemis number already in the system is reported as an error.'],
            ['If any row has an error, nothing is imported. Fix the rows listed and upload the file again.'],
            ['Leave the column headings as they are. Delete none of the required columns.'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        if ($this->instructions) {
            $sheet->getColumnDimension('A')->setWidth(24);
            $sheet->getColumnDimension('B')->setWidth(12);
            $sheet->getColumnDimension('C')->setWidth(90);

            return [1 => ['font' => ['bold' => true]], 13 => ['font' => ['bold' => true]]];
        }

        // Text format keeps typed numbers and dates exactly as entered.
        $sheet->getStyle('A2:J1001')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setWidth($col === 'B' ? 38 : 20);
        }
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return $this->instructions ? 'Instructions' : 'Learners';
    }
}
