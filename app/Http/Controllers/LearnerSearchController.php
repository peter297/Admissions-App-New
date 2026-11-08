<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Find a learner": one search box that looks across every class, branch and year. */
class LearnerSearchController extends Controller
{
    private const LIMIT = 100;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));

        $years = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $yearFilter = $request->query('academic_year_id'); // empty = all years
        $yearId = $years->contains('id', (int) $yearFilter) ? (int) $yearFilter : null;

        $results = collect();
        $tooShort = $q !== '' && mb_strlen($q) < 2;

        if (mb_strlen($q) >= 2) {
            // Escape LIKE wildcards so a search for "50%" finds "50%", not everything.
            $escape = fn (string $s) => addcslashes($s, '%_\\');
            $whole = '%'.$escape($q).'%';
            $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

            $results = Enrollment::query()
                ->whereHas('learner', function ($l) use ($whole, $words, $escape) {
                    $l->where(function ($w) use ($whole, $words, $escape) {
                        // Every word typed must appear in the name, in any order...
                        $w->where(function ($n) use ($words, $escape) {
                            foreach ($words as $word) {
                                $n->where('full_name', 'like', '%'.$escape($word).'%');
                            }
                        })
                        // ...or the whole text matches one of the numbers.
                        ->orWhere('admission_number', 'like', $whole)
                        ->orWhere('assessment_number', 'like', $whole)
                        ->orWhere('kemis_code', 'like', $whole);
                    });
                })
                ->when($user->branch_id, fn ($e) => $e->where('branch_id', $user->branch_id))
                ->when($yearId, fn ($e) => $e->where('academic_year_id', $yearId))
                ->with(['learner', 'classRoom.gradeLevel', 'classRoom.stream', 'branch:id,name', 'academicYear:id,name,is_current'])
                ->orderByDesc('academic_year_id')
                ->limit(self::LIMIT)
                ->get()
                ->filter(fn (Enrollment $e) => $e->learner !== null)
                ->map(fn (Enrollment $e) => [
                    'enrollment_id' => $e->id,
                    'full_name' => $e->learner->full_name,
                    'admission_number' => $e->learner->admission_number,
                    'assessment_number' => $e->learner->assessment_number,
                    'class' => $e->classRoom->name,
                    'class_id' => $e->class_id,
                    'branch' => $e->branch->name,
                    'year' => $e->academicYear->name,
                    'year_id' => $e->academic_year_id,
                    'is_current_year' => (bool) $e->academicYear->is_current,
                    'status' => $e->status,
                ])
                ->values();
        }

        return Inertia::render('learners/search', [
            'q' => $q,
            'academicYearId' => $yearId,
            'years' => $years,
            'results' => $results,
            'tooShort' => $tooShort,
            'limit' => self::LIMIT,
        ]);
    }
}
