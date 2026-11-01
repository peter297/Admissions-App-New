<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Support\ClassListColumns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassRoomController extends Controller
{
    /* ---------------------------------------------------------------
     |  Classes list
     * ------------------------------------------------------------- */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $academicYears = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $year = $this->resolveYear($request, $academicYears);

        $branches = Branch::query()
            ->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId && ! $branches->contains('id', $branchId)) {
            $branchId = null;
        }

        $activeInYear = fn ($q) => $q
            ->where('academic_year_id', $year?->id)
            ->where('status', 'active');

        $classes = ClassRoom::query()
            ->visibleTo($user)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['branch:id,name', 'gradeLevel:id,name,level', 'stream:id,name'])
            ->withCount([
                'enrollments as total' => $activeInYear,
                'enrollments as boys' => fn ($q) => $activeInYear($q)
                    ->whereHas('learner', fn ($l) => $l->where('gender', 'male')),
                'enrollments as girls' => fn ($q) => $activeInYear($q)
                    ->whereHas('learner', fn ($l) => $l->where('gender', 'female')),
            ])
            ->get();

        return Inertia::render('classes/index', [
            'classes' => $this->ordered($classes)->map(fn (ClassRoom $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'branch' => $c->branch->name,
                'total' => $c->total,
                'boys' => $c->boys,
                'girls' => $c->girls,
            ]),
            'branches' => $branches,
            'academicYears' => $academicYears,
            'filters' => [
                'academic_year_id' => $year?->id,
                'branch_id' => $branchId,
            ],
            'canPickBranch' => $branches->count() > 1,
        ]);
    }

    /* ---------------------------------------------------------------
     |  Learners in one class
     * ------------------------------------------------------------- */
    public function show(Request $request, ClassRoom $classRoom): Response
    {
        $user = $request->user();
        $this->authorizeClass($user, $classRoom);

        $classRoom->load(['branch:id,name', 'gradeLevel:id,name,level', 'stream:id,name']);

        $academicYears = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $year = $this->resolveYear($request, $academicYears);

        $learners = Enrollment::query()
            ->where('class_id', $classRoom->id)
            ->where('academic_year_id', $year?->id)
            ->with('learner:id,full_name,assessment_number,admission_number,gender,nationality,kemis_code')
            ->get()
            ->sortBy(fn (Enrollment $e) => $e->learner->full_name)
            ->values()
            ->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'learner_id' => $e->learner_id,
                'assessment_number' => $e->learner->assessment_number,
                'full_name' => $e->learner->full_name,
                'admission_number' => $e->learner->admission_number,
                'gender' => $e->learner->gender,
                'nationality' => $e->learner->nationality,
                'kemis_code' => $e->learner->kemis_code,
                'lunch' => $e->takes_lunch,
                'status' => $e->status,
            ]);

        // Classes this user may move learners into (never the current one).
        $moveTargets = $this->ordered(
            ClassRoom::query()
                ->visibleTo($user)
                ->whereKeyNot($classRoom->id)
                ->with(['branch:id,name', 'gradeLevel:id,name,level', 'stream:id,name'])
                ->get()
        )->map(fn (ClassRoom $c) => [
            'id' => $c->id,
            'label' => $c->name.' ('.$c->branch->name.')',
        ]);

        return Inertia::render('classes/show', [
            'classRoom' => [
                'id' => $classRoom->id,
                'name' => $classRoom->name,
                'branch' => $classRoom->branch->name,
            ],
            'learners' => $learners,
            'moveTargets' => $moveTargets,
            'academicYears' => $academicYears,
            'filters' => ['academic_year_id' => $year?->id],
            // Bulk move is only allowed for the current year, never for history.
            'isCurrentYear' => (bool) $year?->is_current,
            'exportColumns' => ClassListColumns::options(),
        ]);
    }

    /* ---------------------------------------------------------------
     |  Bulk move selected learners to another class
     * ------------------------------------------------------------- */
    public function move(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeClass($user, $classRoom);

        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer'],
            'target_class_id' => [
                'required', 'integer', 'exists:classes,id',
                Rule::notIn([$classRoom->id]),
            ],
        ]);

        // The target must be a class this user is allowed to see.
        $target = ClassRoom::visibleTo($user)->findOrFail($data['target_class_id']);

        $moved = Enrollment::query()
            ->whereIn('id', $data['enrollment_ids'])
            ->where('class_id', $classRoom->id)
            ->whereHas('academicYear', fn ($q) => $q->where('is_current', true))
            ->update([
                'class_id' => $target->id,
                'branch_id' => $target->branch_id,
            ]);

        return back()->with('success', "{$moved} learner(s) moved to {$target->name}.");
    }

    /* ---------------------------------------------------------------
     |  Helpers
     * ------------------------------------------------------------- */
    private function authorizeClass($user, ClassRoom $classRoom): void
    {
        abort_unless(
            ClassRoom::visibleTo($user)->whereKey($classRoom->id)->exists(),
            403
        );
    }

    private function resolveYear(Request $request, Collection $academicYears): ?AcademicYear
    {
        return $academicYears->firstWhere('id', (int) $request->query('academic_year_id'))
            ?? $academicYears->firstWhere('is_current', true)
            ?? $academicYears->first();
    }

    /** Branch, then grade, then stream. */
    private function ordered(Collection $classes): Collection
    {
        return $classes->sortBy([
            fn ($a, $b) => $a->branch->name <=> $b->branch->name,
            fn ($a, $b) => $a->gradeLevel->level <=> $b->gradeLevel->level,
            fn ($a, $b) => $a->stream->name <=> $b->stream->name,
        ])->values();
    }
}
