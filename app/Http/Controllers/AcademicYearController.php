<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Term;
use App\Support\YearRollover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(Request $request, YearRollover $rollover): Response
    {
        $years = AcademicYear::orderByDesc('name')->get();
        $terms = Term::orderBy('number')->get()->groupBy('academic_year_id');
        $learners = Enrollment::query()
            ->selectRaw('academic_year_id, count(*) as total')
            ->groupBy('academic_year_id')
            ->pluck('total', 'academic_year_id');

        $rows = $years->map(fn (AcademicYear $y) => [
            'id' => $y->id,
            'name' => $y->name,
            'is_current' => (bool) $y->is_current,
            'learners' => (int) ($learners[$y->id] ?? 0),
            'terms' => ($terms[$y->id] ?? collect())->map(fn (Term $t) => [
                'id' => $t->id,
                'number' => $t->number,
                'is_current' => (bool) $t->is_current,
            ])->values(),
        ])->values();

        // Any year except the current one can be a rollover target. Learners already enrolled in it are skipped.
        $targets = $rows->filter(fn ($y) => ! $y['is_current'])
            ->map(fn ($y) => ['id' => $y['id'], 'name' => $y['name']])
            ->values();

        $current = $years->firstWhere('is_current', true);
        $preview = null;
        $targetId = $request->integer('target_year_id') ?: null;

        if ($current && $targetId && $targets->contains('id', $targetId)) {
            $plan = $rollover->plan($current);
            unset($plan['map']);
            $preview = $plan + ['target' => $targets->firstWhere('id', $targetId)];
        }

        return Inertia::render('academic-years/index', [
            'years' => $rows,
            'currentYear' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'targets' => $targets,
            'targetYearId' => $targetId,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:academic_years,name'],
        ]);

        DB::transaction(function () use ($data) {
            $year = AcademicYear::create([
                'name' => $data['name'],
                'is_current' => ! AcademicYear::exists(), // the very first year starts as current
            ]);

            foreach ([1, 2, 3] as $n) {
                Term::create([
                    'academic_year_id' => $year->id,
                    'number' => $n,
                    'is_current' => $year->is_current && $n === 1,
                ]);
            }
        });

        return back()->with('success', "Academic year {$data['name']} created with 3 terms.");
    }

    public function setCurrent(AcademicYear $academicYear, YearRollover $rollover): RedirectResponse
    {
        DB::transaction(fn () => $rollover->makeCurrent($academicYear));

        return back()->with('success', "{$academicYear->name} is now the current academic year.");
    }

    public function setTerm(Term $term): RedirectResponse
    {
        abort_unless($term->academicYear()->where('is_current', true)->exists(), 403);

        DB::transaction(function () use ($term) {
            Term::query()->update(['is_current' => false]);
            $term->update(['is_current' => true]);
        });

        return back()->with('success', "Term {$term->number} is now the current term.");
    }

    public function rollover(Request $request, YearRollover $rollover): RedirectResponse
    {
        $data = $request->validate([
            'target_year_id' => ['required', 'exists:academic_years,id'],
            'make_current' => ['required', 'boolean'],
        ]);

        $from = AcademicYear::where('is_current', true)->first();
        if (! $from) {
            throw ValidationException::withMessages(['target_year_id' => 'There is no current academic year to roll over from.']);
        }

        $to = AcademicYear::findOrFail($data['target_year_id']);
        if ($to->id === $from->id) {
            throw ValidationException::withMessages(['target_year_id' => 'Choose a different year from the current one.']);
        }

        $result = $rollover->execute($from, $to, $data['make_current']);

        return redirect()->route('academic-years.index')->with(
            'success',
            "Rollover complete: {$result['promoted']} learners moved to {$to->name}, {$result['graduated']} graduated."
            .($result['skipped'] ? " {$result['skipped']} were already enrolled in {$to->name} and were left unchanged." : '')
        );
    }
}
