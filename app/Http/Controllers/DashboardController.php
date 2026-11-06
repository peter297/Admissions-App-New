<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Branch;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Stream;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** One set of counts, reused for the totals and for each branch. */
    private const COUNTS = "count(*) as total,
        coalesce(sum(learners.gender = 'male'), 0) as boys,
        coalesce(sum(learners.gender = 'female'), 0) as girls,
        coalesce(sum(enrollments.takes_lunch = 1), 0) as lunch,
        coalesce(sum(enrollments.bus_id is not null or enrollments.transport_route_id is not null), 0) as transport";

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        /* ---- filters ---- */
        $years = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $year = $years->firstWhere('id', (int) $request->query('academic_year_id'))
            ?? $years->firstWhere('is_current', true)
            ?? $years->first();

        // Users tied to a branch (Deputy Principal, branch admins) only ever see that branch.
        $branches = Branch::query()
            ->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId && ! $branches->contains('id', $branchId)) {
            $branchId = null;
        }
        $gradeId = $request->integer('grade_level_id') ?: null;

        /* ---- one base query, so every number on the page obeys the same filters ---- */
        $base = fn () => Enrollment::query()
            ->join('learners', 'learners.id', '=', 'enrollments.learner_id')
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->whereNull('learners.deleted_at')
            ->where('enrollments.academic_year_id', $year?->id)
            ->when($user->branch_id, fn ($q) => $q->where('enrollments.branch_id', $user->branch_id))
            ->when($branchId, fn ($q) => $q->where('enrollments.branch_id', $branchId))
            ->when($gradeId, fn ($q) => $q->where('classes.grade_level_id', $gradeId));

        $active = fn () => $base()->where('enrollments.status', 'active');

        $statuses = $base()
            ->selectRaw('enrollments.status, count(*) as total')
            ->groupBy('enrollments.status')
            ->pluck('total', 'status');

        $totals = $active()->selectRaw(self::COUNTS)->first();

        /* ---- by branch ---- */
        $byBranch = $active()
            ->selectRaw('enrollments.branch_id, '.self::COUNTS)
            ->groupBy('enrollments.branch_id')
            ->get()
            ->keyBy('branch_id');

        $branchRows = $branches
            ->when($branchId, fn ($c) => $c->where('id', $branchId))
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'total' => (int) ($byBranch[$b->id]->total ?? 0),
                'boys' => (int) ($byBranch[$b->id]->boys ?? 0),
                'girls' => (int) ($byBranch[$b->id]->girls ?? 0),
                'lunch' => (int) ($byBranch[$b->id]->lunch ?? 0),
                'transport' => (int) ($byBranch[$b->id]->transport ?? 0),
            ])->values();

        /* ---- by grade and stream ---- */
        $streams = Stream::orderBy('name')->get(['id', 'name']);

        $cells = $active()
            ->selectRaw('classes.grade_level_id, classes.stream_id, count(*) as total')
            ->groupBy('classes.grade_level_id', 'classes.stream_id')
            ->get()
            ->groupBy('grade_level_id');

        // Only the grades that exist in the branches being shown.
        $gradeIds = ClassRoom::query()
            ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($gradeId, fn ($q) => $q->where('grade_level_id', $gradeId))
            ->pluck('grade_level_id')
            ->unique();

        $gradeRows = GradeLevel::whereIn('id', $gradeIds)->orderBy('level')->get()->map(function (GradeLevel $g) use ($cells, $streams) {
            $row = $cells->get($g->id, collect())->keyBy('stream_id');
            $perStream = $streams->mapWithKeys(fn ($s) => [$s->id => (int) ($row[$s->id]->total ?? 0)]);

            return [
                'id' => $g->id,
                'name' => $g->name,
                'streams' => $perStream,
                'total' => $perStream->sum(),
            ];
        })->values();

        /* ---- admissions snapshot (only for people who may see admissions) ---- */
        $admissions = null;
        if ($user->can('admissions.view')) {
            $a = Applicant::query()
                ->visibleTo($user)
                ->filter(['academic_year_id' => $year?->id, 'branch_id' => $branchId, 'grade_level_id' => $gradeId])
                ->selectRaw("count(*) as total,
                    coalesce(sum(result = 'pending'), 0) as pending,
                    coalesce(sum(result = 'passed' and promoted_at is null), 0) as passed,
                    coalesce(sum(result = 'failed'), 0) as failed,
                    coalesce(sum(promoted_at is not null), 0) as admitted")
                ->first();

            $admissions = [
                'total' => (int) $a->total, 'pending' => (int) $a->pending, 'passed' => (int) $a->passed,
                'failed' => (int) $a->failed, 'admitted' => (int) $a->admitted,
            ];
        }

        return Inertia::render('dashboard', [
            'year' => $year?->name,
            'years' => $years,
            'branches' => $branches,
            'grades' => GradeLevel::orderBy('level')->get(['id', 'name']),
            'filters' => [
                'academic_year_id' => $year?->id,
                'branch_id' => $branchId,
                'grade_level_id' => $gradeId,
            ],
            'canPickBranch' => $branches->count() > 1,
            'totals' => [
                'total' => (int) $totals->total,
                'boys' => (int) $totals->boys,
                'girls' => (int) $totals->girls,
                'lunch' => (int) $totals->lunch,
                'transport' => (int) $totals->transport,
            ],
            'statuses' => [
                'inactive' => (int) ($statuses['inactive'] ?? 0),
                'transferred' => (int) ($statuses['transferred'] ?? 0),
                'graduated' => (int) ($statuses['graduated'] ?? 0),
            ],
            'branchRows' => $branchRows,
            'streams' => $streams,
            'gradeRows' => $gradeRows,
            'admissions' => $admissions,
        ]);
    }
}
