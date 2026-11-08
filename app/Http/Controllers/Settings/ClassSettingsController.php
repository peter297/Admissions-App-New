<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClassSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $grades = GradeLevel::orderBy('level')->get();
        $streams = Stream::orderBy('name')->get();
        $classes = ClassRoom::all()->keyBy(fn (ClassRoom $c) => "{$c->branch_id}-{$c->grade_level_id}-{$c->stream_id}");

        $year = AcademicYear::where('is_current', true)->first();

        // Learners ever enrolled (decides whether a class can be removed) and active this year.
        $ever = Enrollment::selectRaw('class_id, count(*) as total')->groupBy('class_id')->pluck('total', 'class_id');
        $now = Enrollment::where('academic_year_id', $year?->id)->where('status', 'active')
            ->selectRaw('class_id, count(*) as total')->groupBy('class_id')->pluck('total', 'class_id');

        $branches = Branch::orderBy('name')->get()->map(function (Branch $b) use ($grades, $streams, $classes, $ever, $now) {
            $finalLevel = $grades->firstWhere('id', $b->final_grade_level_id)?->level ?? 0;
            $firstLevel = $grades->firstWhere('id', $b->first_grade_level_id)?->level ?? 1;

            return [
                'id' => $b->id,
                'name' => $b->name,
                'first_grade_id' => $b->first_grade_level_id ?? $grades->firstWhere('level', 1)?->id,
                'final_grade_id' => $b->final_grade_level_id,
                // Only grades up to the branch's final grade are shown.
                'grades' => $grades->where('level', '>=', $firstLevel)->where('level', '<=', $finalLevel)->map(fn (GradeLevel $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'cells' => $streams->map(function (Stream $s) use ($b, $g, $classes, $ever, $now) {
                        $c = $classes->get("{$b->id}-{$g->id}-{$s->id}");

                        return [
                            'stream_id' => $s->id,
                            'class_id' => $c?->id,
                            'learners' => (int) ($c ? ($now[$c->id] ?? 0) : 0),
                            'can_delete' => $c ? ! isset($ever[$c->id]) : false,
                        ];
                    })->values(),
                ])->values(),
            ];
        });

        $streamRows = $streams->map(fn (Stream $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'classes' => ClassRoom::where('stream_id', $s->id)->count(),
        ])->map(fn ($r) => $r + ['can_delete' => $r['classes'] === 0]);

        return Inertia::render('school-settings/classes', [
            'branches' => $branches,
            'streams' => $streams->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'streamRows' => $streamRows->values(),
            'allGrades' => $grades->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values(),
            'year' => $year?->name,
            'isSuperAdmin' => $request->user()->hasRole('Super Admin'),
        ]);
    }

    /* ---------------- Streams ---------------- */
    public function storeStream(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:streams,name'],
            'create_classes' => ['required', 'boolean'],
        ]);

        $stream = Stream::create(['name' => $data['name']]);
        $created = 0;

        // Convenience: give every branch a class of this stream in each of its grades.
        if ($data['create_classes']) {
            $grades = GradeLevel::orderBy('level')->get();

            foreach (Branch::all() as $b) {
                $finalLevel = $grades->firstWhere('id', $b->final_grade_level_id)?->level ?? 0;
                $firstLevel = $grades->firstWhere('id', $b->first_grade_level_id)?->level ?? 1;
                foreach ($grades->where('level', '>=', $firstLevel)->where('level', '<=', $finalLevel) as $g) {
                    ClassRoom::firstOrCreate(['branch_id' => $b->id, 'grade_level_id' => $g->id, 'stream_id' => $stream->id]);
                    $created++;
                }
            }
        }

        return back()->with('success', "Stream {$stream->name} added".($created ? " with {$created} classes." : '.'));
    }

    public function updateStream(Request $request, Stream $stream): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('streams', 'name')->ignore($stream->id)],
        ]);

        $stream->update($data);

        return back()->with('success', 'Stream renamed.');
    }

    public function destroyStream(Stream $stream): RedirectResponse
    {
        if (ClassRoom::where('stream_id', $stream->id)->exists()) {
            throw ValidationException::withMessages(['action' => "Stream {$stream->name} still has classes. Remove those classes first."]);
        }

        $stream->delete();

        return back()->with('success', "Stream {$stream->name} deleted.");
    }

    /* ---------------- Classes ---------------- */
    public function storeClass(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'stream_id' => ['required', 'exists:streams,id'],
        ]);

        $branch = Branch::findOrFail($data['branch_id']);
        $grade = GradeLevel::findOrFail($data['grade_level_id']);
        $final = GradeLevel::find($branch->final_grade_level_id);

        if ($final && $grade->level > $final->level) {
            throw ValidationException::withMessages(['action' => "{$branch->name} ends at {$final->name}. Change its final grade first."]);
        }

        $first = GradeLevel::find($branch->first_grade_level_id);

        if ($first && $grade->level < $first->level) {
            throw ValidationException::withMessages(['action' => "{$branch->name} starts at {$first->name}. Change its first grade first."]);
        }

        $class = ClassRoom::firstOrCreate($data);

        return back()->with('success', $class->wasRecentlyCreated ? 'Class added.' : 'That class already exists.');
    }

    public function destroyClass(ClassRoom $classRoom): RedirectResponse
    {
        if (Enrollment::where('class_id', $classRoom->id)->exists()) {
            throw ValidationException::withMessages([
                'action' => "{$classRoom->name} has learners, or had them in a past year, so it cannot be removed. Move the learners first.",
            ]);
        }

        $classRoom->delete();

        return back()->with('success', 'Class removed.');
    }

    /* ---------------- Branch first grade ---------------- */
    public function updateFirstGrade(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate(['grade_level_id' => ['required', 'exists:grade_levels,id']]);

        $new = GradeLevel::findOrFail($data['grade_level_id']);
        $final = GradeLevel::find($branch->final_grade_level_id);

        if ($final && $new->level > $final->level) {
            throw ValidationException::withMessages(['action' => 'The first grade cannot come after the final grade.']);
        }

        $below = ClassRoom::where('branch_id', $branch->id)
            ->whereHas('gradeLevel', fn ($q) => $q->where('level', '<', $new->level))
            ->exists();

        if ($below) {
            throw ValidationException::withMessages([
                'action' => "{$branch->name} still has classes below {$new->name}. Remove them first.",
            ]);
        }

        $branch->update(['first_grade_level_id' => $new->id]);

        return back()->with('success', "{$branch->name} now starts at {$new->name}. Use Create missing classes to add its classes.");
    }

    /** One click: every grade between the branch's first and final grade gets a class in every stream. */
    public function fillClasses(Branch $branch): RedirectResponse
    {
        $grades = GradeLevel::orderBy('level')->get();
        $first = $grades->firstWhere('id', $branch->first_grade_level_id)?->level ?? 1;
        $final = $grades->firstWhere('id', $branch->final_grade_level_id)?->level ?? 0;

        $created = 0;

        foreach ($grades->where('level', '>=', $first)->where('level', '<=', $final) as $g) {
            foreach (Stream::all() as $s) {
                $class = ClassRoom::firstOrCreate(['branch_id' => $branch->id, 'grade_level_id' => $g->id, 'stream_id' => $s->id]);
                if ($class->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return back()->with('success', $created
            ? "{$created} classes created for {$branch->name}."
            : "{$branch->name} already has every class.");
    }

    /* ---------------- Branch final grade ---------------- */
    public function updateFinalGrade(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate(['grade_level_id' => ['required', 'exists:grade_levels,id']]);

        $new = GradeLevel::findOrFail($data['grade_level_id']);

        $first = GradeLevel::find($branch->first_grade_level_id);
        if ($first && $new->level < $first->level) {
            throw ValidationException::withMessages(['action' => 'The final grade cannot come before the first grade.']);
        }

        $above = ClassRoom::where('branch_id', $branch->id)
            ->whereHas('gradeLevel', fn ($q) => $q->where('level', '>', $new->level))
            ->exists();

        if ($above) {
            throw ValidationException::withMessages([
                'action' => "{$branch->name} still has classes above {$new->name}. Remove them first.",
            ]);
        }

        $branch->update(['final_grade_level_id' => $new->id]);

        return back()->with('success', "{$branch->name} now ends at {$new->name}. This takes effect at the next year-end rollover.");
    }
}
