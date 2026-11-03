<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

// Laravel Excel 4 requires a multi-sheet export to also implement the Export marker interface.
class LearnerImportTemplate implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new LearnerImportTemplateSheet(false),
            new LearnerImportTemplateSheet(true),
        ];
    }
}
