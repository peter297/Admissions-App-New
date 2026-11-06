<?php

namespace App\Http\Controllers;

use App\Exports\ClassListExport;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Support\ApplicantColumns;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class AdmissionsExportController extends Controller
{
    private const RESULT_LABELS = [
        'all' => 'All results',
        'pending' => 'Pending',
        'passed' => 'Passed, not yet admitted',
        'failed' => 'Failed',
        'admitted' => 'Admitted',
    ];

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'branch_id' => ['nullable', 'integer'],
            'grade_level_id' => ['nullable', 'integer'],
            'result' => ['nullable', Rule::in(array_keys(self::RESULT_LABELS))],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', Rule::in(array_keys(ApplicantColumns::all()))],
            'include_title' => ['nullable', 'boolean'],
        ]);

        $result = $data['result'] ?? 'all';
        $columns = ApplicantColumns::normalise($data['columns']);

        $applicants = Applicant::query()
            ->visibleTo($request->user())
            ->filter([
                'academic_year_id' => $data['academic_year_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'grade_level_id' => $data['grade_level_id'] ?? null,
                'result' => $result,
            ])
            ->with(['branch:id,name', 'gradeLevel:id,name', 'academicYear:id,name'])
            ->orderBy('full_name')
            ->get();

        $year = AcademicYear::find($data['academic_year_id']);
        $branch = ! empty($data['branch_id']) ? Branch::find($data['branch_id'])?->name : 'All branches';
        $grade = ! empty($data['grade_level_id']) ? GradeLevel::find($data['grade_level_id'])?->name : 'All grades';

        $title = "Admissions {$year->name}";
        $subtitle = "{$branch} | {$grade} | ".self::RESULT_LABELS[$result]." | {$applicants->count()} applicants";

        $headings = ApplicantColumns::headings($columns);
        $rows = ApplicantColumns::rows($applicants, $columns);
        $filename = Str::slug("admissions {$year->name} {$branch} {$grade} {$result}");

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
