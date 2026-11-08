<?php

namespace App\Http\Controllers;

use App\Exports\ClassListExport;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Term;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Historical data: who left the school in a given academic year and term,
 * on what date, and why. It reads the status, term, date and reason saved
 * on each enrollment when a learner is made Inactive or Transferred.
 */
class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $this->load($request);

        return Inertia::render('history/index', [
            'years' => $f['years'],
            'terms' => $f['terms'],
            'branches' => $f['branches'],
            'filters' => $f['filters'],
            'canPickBranch' => $f['branches']->count() > 1,
            'summary' => [
                'total' => $f['rows']->count(),
                'boys' => $f['rows']->where('gender', 'male')->count(),
                'girls' => $f['rows']->where('gender', 'female')->count(),
                'inactive' => $f['rows']->where('status', 'inactive')->count(),
                'transferred' => $f['rows']->where('status', 'transferred')->count(),
            ],
            'rows' => $f['rows']->values(),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate(['format' => ['required', Rule::in(['xlsx', 'pdf'])]]);

        $f = $this->load($request);

        $headings = ['No.', 'Name', 'Admission No.', 'Gender', 'Class', 'Branch', 'Status', 'Date left', 'Term', 'Reason'];
        $rows = $f['rows']->values()->map(fn ($r, $i) => [
            (string) ($i + 1), $r['full_name'], $r['admission_number'], ucfirst($r['gender']), $r['class'],
            $r['branch'], ucfirst($r['status']), $r['left_on'] ?? '', $r['term'], $r['reason'] ?? '',
        ])->all();

        $title = "Learners who left - {$f['year']?->name}, {$f['termLabel']}";
        $subtitle = "{$f['branchLabel']} | {$f['rows']->count()} learners";
        $filename = Str::slug("learners who left {$f['year']?->name} {$f['termLabel']} {$f['branchLabel']}");

        if ($request->query('format') === 'xlsx') {
            return Excel::download(new ClassListExport($headings, $rows, 'Learners who left', $title, $subtitle), "{$filename}.xlsx");
        }

        return Pdf::loadView('exports.class-list', compact('title', 'subtitle', 'headings', 'rows'))
            ->setPaper('a4', 'landscape')
            ->download("{$filename}.pdf");
    }

    private function load(Request $request): array
    {
        $user = $request->user();

        $years = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $year = $years->firstWhere('id', (int) $request->query('academic_year_id'))
            ?? $years->firstWhere('is_current', true)
            ?? $years->first();

        $terms = Term::where('academic_year_id', $year?->id)->orderBy('number')->get(['id', 'number']);

        // "all", "none" (term was never recorded) or a term id.
        $termParam = (string) $request->query('term', 'all');
        $term = match (true) {
            $termParam === 'none' => 'none',
            $terms->contains('id', (int) $termParam) => (int) $termParam,
            default => 'all',
        };

        $status = in_array($request->query('status'), ['inactive', 'transferred'], true) ? $request->query('status') : 'all';

        $branches = Branch::query()
            ->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);
        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId && ! $branches->contains('id', $branchId)) {
            $branchId = null;
        }

        $rows = Enrollment::query()
            ->where('academic_year_id', $year?->id)
            ->whereIn('status', ['inactive', 'transferred'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($term === 'none', fn ($q) => $q->whereNull('status_changed_term_id'))
            ->when(is_int($term), fn ($q) => $q->where('status_changed_term_id', $term))
            ->with(['learner', 'classRoom.gradeLevel', 'classRoom.stream', 'branch:id,name', 'statusTerm:id,number'])
            ->get()
            ->filter(fn (Enrollment $e) => $e->learner !== null)
            ->sortBy(fn (Enrollment $e) => $e->learner->full_name)
            ->sortByDesc(fn (Enrollment $e) => $e->status_changed_at?->timestamp ?? 0) // newest first; ties stay A-Z
            ->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'full_name' => $e->learner->full_name,
                'admission_number' => $e->learner->admission_number,
                'gender' => $e->learner->gender,
                'class' => $e->classRoom->name,
                'branch' => $e->branch->name,
                'status' => $e->status,
                'left_on' => $e->status_changed_at?->format('d/m/Y'),
                'term' => $e->statusTerm ? "Term {$e->statusTerm->number}" : 'Not recorded',
                'reason' => $e->status_note,
            ]);

        return [
            'years' => $years,
            'year' => $year,
            'terms' => $terms,
            'branches' => $branches,
            'filters' => [
                'academic_year_id' => $year?->id,
                'term' => $term,
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'rows' => $rows,
            'termLabel' => match (true) {
                $term === 'none' => 'term not recorded',
                is_int($term) => 'Term '.$terms->firstWhere('id', $term)->number,
                default => 'all terms',
            },
            'branchLabel' => $branchId ? $branches->firstWhere('id', $branchId)->name : 'All branches',
        ];
    }
}
