<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Support\ClassListColumns;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alumni are learners whose enrollment status is "graduated". The rollover sets that
 * status on learners in a branch's final grade, so a cohort is simply
 * (branch + academic year), named after the branch's final grade: "Grade 9 Alumni 2026".
 */
class AlumniController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $groups = Enrollment::query()
            ->join('learners', 'learners.id', '=', 'enrollments.learner_id')
            ->where('enrollments.status', 'graduated')
            ->whereNull('learners.deleted_at')
            ->when($user->branch_id, fn ($q) => $q->where('enrollments.branch_id', $user->branch_id))
            ->selectRaw("enrollments.academic_year_id, enrollments.branch_id, count(*) as total,
                         sum(learners.gender = 'male') as boys, sum(learners.gender = 'female') as girls")
            ->groupBy('enrollments.academic_year_id', 'enrollments.branch_id')
            ->get();

        $years = AcademicYear::pluck('name', 'id');
        $branches = Branch::get()->keyBy('id');
        $grades = GradeLevel::pluck('name', 'id');

        $cohorts = $groups
            ->map(function ($g) use ($years, $branches, $grades) {
                $branch = $branches[$g->branch_id];

                return [
                    'academic_year_id' => $g->academic_year_id,
                    'year' => $years[$g->academic_year_id],
                    'branch_id' => $branch->id,
                    'branch' => $branch->name,
                    'name' => $grades[$branch->final_grade_level_id].' Alumni '.$years[$g->academic_year_id],
                    'total' => (int) $g->total,
                    'boys' => (int) $g->boys,
                    'girls' => (int) $g->girls,
                ];
            })
            ->sortBy([['year', 'desc'], ['branch', 'asc']])
            ->values();

        return Inertia::render('alumni/index', ['cohorts' => $cohorts]);
    }

    public function show(Request $request, AcademicYear $academicYear, Branch $branch): Response
    {
        $this->authorizeBranch($request, $branch);

        $learners = $this->enrollments($academicYear, $branch)
            ->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'assessment_number' => $e->learner->assessment_number,
                'full_name' => $e->learner->full_name,
                'admission_number' => $e->learner->admission_number,
                'gender' => $e->learner->gender,
                'nationality' => $e->learner->nationality,
                'kemis_code' => $e->learner->kemis_code,
                'class' => $e->classRoom->name,
                'graduated_on' => $e->status_changed_at?->format('d/m/Y'),
            ]);

        return Inertia::render('alumni/show', [
            'cohort' => [
                'name' => $this->cohortName($academicYear, $branch),
                'branch' => $branch->name,
                'year' => $academicYear->name,
                'academic_year_id' => $academicYear->id,
                'branch_id' => $branch->id,
            ],
            'learners' => $learners,
            'exportColumns' => ClassListColumns::options(),
        ]);
    }

    /* ---------------- shared with the export controller ---------------- */
    public function enrollments(AcademicYear $year, Branch $branch)
    {
        return Enrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('branch_id', $branch->id)
            ->where('status', 'graduated')
            ->with(['learner', 'classRoom.gradeLevel', 'classRoom.stream', 'transportRoute:id,name', 'bus:id,number_plate', 'activities:id,name'])
            ->get()
            ->filter(fn (Enrollment $e) => $e->learner !== null) // skip soft-deleted learners
            ->sortBy(fn (Enrollment $e) => $e->learner->full_name)
            ->values();
    }

    public function cohortName(AcademicYear $year, Branch $branch): string
    {
        return GradeLevel::find($branch->final_grade_level_id)->name.' Alumni '.$year->name;
    }

    public function authorizeBranch(Request $request, Branch $branch): void
    {
        abort_if($request->user()->branch_id && $request->user()->branch_id !== $branch->id, 403);
    }
}
