<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Extending StringValueBinder writes every cell as text, so assessment and
 * Kemis numbers keep their leading zeros and never turn into 1.23E+10.
 *
 * When $heading is given, the sheet starts with the class name (row 1), a line
 * of details (row 2) and a blank row, and the column headings move to row 4.
 */
class ClassListExport extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithStyles, WithTitle
{
    public function __construct(
        private array $headings,
        private array $rows,
        private string $sheetTitle,
        private ?string $heading = null,
        private ?string $subheading = null,
    ) {
    }

    public function array(): array
    {
        $out = [];

        if ($this->heading !== null) {
            $out[] = [$this->heading];
            $out[] = [$this->subheading ?? ''];
            $out[] = [];
        }

        $out[] = $this->headings;

        return array_merge($out, $this->rows);
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [$this->headingRow() => ['font' => ['bold' => true]]];

        if ($this->heading !== null) {
            // Merge across the table so a long title does not stretch column A.
            if (count($this->headings) > 1) {
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings));
                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
            }

            $styles[1] = ['font' => ['bold' => true, 'size' => 14]];
            $styles[2] = ['font' => ['color' => ['rgb' => '555555']]];
        }

        return $styles;
    }

    public function title(): string
    {
        // Excel sheet names: max 31 characters, no \ / ? * [ ] :
        return Str::limit(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $this->sheetTitle), 31, '');
    }

    private function headingRow(): int
    {
        return $this->heading !== null ? 4 : 1;
    }
}
