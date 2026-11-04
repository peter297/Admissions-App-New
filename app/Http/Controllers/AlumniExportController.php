<?php

namespace App\Http\Controllers;

use App\Exports\ClassListExport;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Support\ClassListColumns;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class AlumniExportController extends Controller
{
    public function __invoke(Request $request, AcademicYear $academicYear, Branch $branch, AlumniController $alumni)
    {
        $alumni->authorizeBranch($request, $branch);

        $data = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', Rule::in(array_keys(ClassListColumns::all()))],
            'include_title' => ['nullable', 'boolean'],
        ]);

        $columns = ClassListColumns::normalise($data['columns']);
        $enrollments = $alumni->enrollments($academicYear, $branch);

        $headings = ClassListColumns::headings($columns);
        $rows = ClassListColumns::rows($enrollments, $columns);

        $title = $alumni->cohortName($academicYear, $branch);
        $subtitle = "{$branch->name} | {$academicYear->name} | {$enrollments->count()} learners";
        $filename = Str::slug("{$title} {$branch->name}");

        if ($data['format'] === 'xlsx') {
            return Excel::download(
                new ClassListExport($headings, $rows, $title, $request->boolean('include_title', true) ? $title : null, $subtitle),
                "{$filename}.xlsx"
            );
        }

        return Pdf::loadView('exports.class-list', compact('title', 'subtitle', 'headings', 'rows'))
            ->setPaper('a4', count($columns) > 7 ? 'landscape' : 'portrait')
            ->download("{$filename}.pdf");
    }
}
