<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Term;
use App\Support\LearnerReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, LearnerReport $builder): Response
    {
        $p = $this->params($request);

        $report = null;
        $error = null;

        try {
            $report = $p['year'] ? $builder->build($p['year'], $p['period'], $p['term'], $p['month'], $p['selected']) : null;
        } catch (ValidationException $e) {
            $error = $e->validator->errors()->first();
        }

        return Inertia::render('reports/index', [
            'years' => $p['years'],
            'terms' => $p['terms'],
            'branches' => $p['branches'],
            'canPickBranch' => $p['branches']->count() > 1,
            'filters' => [
                'period' => $p['period'],
                'academic_year_id' => $p['year']?->id,
                'term_id' => $p['term']?->id,
                'month' => $p['month'],
                'branch_id' => $p['branchId'],
            ],
            'report' => $report,
            'error' => $error,
        ]);
    }

    public function pdf(Request $request, LearnerReport $builder)
    {
        $p = $this->params($request);
        abort_unless($p['year'], 404);

        $report = $builder->build($p['year'], $p['period'], $p['term'], $p['month'], $p['selected']);

        $branchLabel = $p['branchId'] ? $p['selected']->first()->name : 'All branches';
        $title = 'Learners on roll and learners who left';
        $subtitle = ucfirst($p['period']).' report | '.$report['period_label'].' | '.$branchLabel;

        $pdf = Pdf::loadView('reports.learners', compact('report', 'title', 'subtitle'))->setPaper('a4', 'portrait');

        return $pdf->download(Str::slug("learners report {$p['period']} {$report['period_label']} {$branchLabel}").'.pdf');
    }

    /** One place that reads the filters, so the preview and the PDF can never disagree. */
    private function params(Request $request): array
    {
        $user = $request->user();

        $years = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $year = $years->firstWhere('id', (int) $request->query('academic_year_id'))
            ?? $years->firstWhere('is_current', true)
            ?? $years->first();

        $terms = Term::where('academic_year_id', $year?->id)->orderBy('number')->get();
        $term = $terms->firstWhere('id', (int) $request->query('term_id'))
            ?? $terms->firstWhere('is_current', true)
            ?? $terms->first();

        $period = in_array($request->query('period'), ['monthly', 'termly', 'yearly'], true) ? $request->query('period') : 'termly';

        $month = (int) $request->query('month');
        $month = ($month >= 1 && $month <= 12) ? $month : (int) now()->month;

        $branches = Branch::query()
            ->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId && ! $branches->contains('id', $branchId)) {
            $branchId = null;
        }

        return [
            'years' => $years, 'year' => $year, 'terms' => $terms, 'term' => $term,
            'period' => $period, 'month' => $month,
            'branches' => $branches, 'branchId' => $branchId,
            'selected' => $branchId ? $branches->where('id', $branchId)->values() : $branches,
        ];
    }
}
