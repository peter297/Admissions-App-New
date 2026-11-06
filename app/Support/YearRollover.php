<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Year-end rollover.
 *
 *  - Active learners move to the same stream of the next grade in the new year.
 *  - Active learners in a branch's final grade become "graduated" (alumni) in the old year.
 *  - Inactive and transferred learners are left behind in the old year.
 *  - Learners who already have an enrollment in the new year (for example, applicants
 *    admitted early) are skipped and left exactly as they are.
 *
 * plan() only reads. execute() writes everything inside one transaction.
 */
class YearRollover
{
    public function plan(AcademicYear $from): array
    {
        $counts = Enrollment::query()
            ->where('academic_year_id', $from->id)
            ->where('status', 'active')
            ->selectRaw('class_id, count(*) as total')
            ->groupBy('class_id')
            ->pluck('total', 'class_id');

        $classes = ClassRoom::query()
            ->with(['branch:id,name,final_grade_level_id', 'gradeLevel:id,name,level', 'stream:id,name'])
            ->get();

        // Look a class up by branch + grade level + stream.
        $byKey = $classes->keyBy(fn (ClassRoom $c) => "{$c->branch_id}-{$c->gradeLevel->level}-{$c->stream_id}");

        $rows = [];
        $map = [];
        $promoting = $graduating = $missing = 0;

        $withLearners = $classes
            ->filter(fn (ClassRoom $c) => $counts->has($c->id))
            ->sortBy([
                fn ($a, $b) => $a->branch->name <=> $b->branch->name,
                fn ($a, $b) => $a->gradeLevel->level <=> $b->gradeLevel->level,
                fn ($a, $b) => $a->stream->name <=> $b->stream->name,
            ]);

        foreach ($withLearners as $c) {
            $total = (int) $counts[$c->id];
            [$type, $target] = $this->destination($c, $byKey);

            $map[$c->id] = ['type' => $type, 'target_id' => $target?->id, 'branch_id' => $target?->branch_id];

            match ($type) {
                'promote' => $promoting += $total,
                'graduate' => $graduating += $total,
                default => $missing += $total,
            };

            $rows[] = [
                'class' => $c->name,
                'branch' => $c->branch->name,
                'total' => $total,
                'type' => $type,
                'destination' => match ($type) {
                    'promote' => $target->name,
                    'graduate' => 'Alumni (graduates)',
                    default => 'No class in the next grade',
                },
            ];
        }

        return [
            'rows' => $rows,
            'map' => $map,
            'promoting' => $promoting,
            'graduating' => $graduating,
            'missing' => $missing,
            'not_carried' => Enrollment::where('academic_year_id', $from->id)->where('status', '!=', 'active')->count(),
        ];
    }

    /** @return array{promoted: int, graduated: int, skipped: int} */
    public function execute(AcademicYear $from, AcademicYear $to, bool $makeCurrent): array
    {
        return DB::transaction(function () use ($from, $to, $makeCurrent) {
            // Lock both years so two people cannot run a rollover at the same time.
            AcademicYear::whereIn('id', [$from->id, $to->id])->lockForUpdate()->get();

            $plan = $this->plan($from);

            if ($plan['missing'] > 0) {
                throw ValidationException::withMessages([
                    'target_year_id' => 'Some classes have no matching class in the next grade. Fix the classes first.',
                ]);
            }

            $lastTerm = Term::where('academic_year_id', $from->id)->orderByDesc('number')->first();
            $promoted = 0;
            $graduated = 0;
            $skipped = 0;

            Enrollment::query()
                ->where('academic_year_id', $from->id)
                ->where('status', 'active')
                ->with('activities:id')
                ->chunkById(200, function (Collection $chunk) use ($plan, $to, $lastTerm, &$promoted, &$graduated, &$skipped) {
                    foreach ($chunk as $e) {
                        $dest = $plan['map'][$e->class_id];

                        if ($dest['type'] === 'graduate') {
                            $e->update([
                                'status' => 'graduated',
                                'status_changed_term_id' => $lastTerm?->id,
                                'status_changed_at' => now()->toDateString(),
                            ]);
                            $graduated++;
                            continue;
                        }

                        if (Enrollment::where('learner_id', $e->learner_id)->where('academic_year_id', $to->id)->exists()) {
                            $skipped++;
                            continue;
                        }

                        $new = Enrollment::create([
                            'learner_id' => $e->learner_id,
                            'academic_year_id' => $to->id,
                            'class_id' => $dest['target_id'],
                            'branch_id' => $dest['branch_id'],
                            'status' => 'active',
                            'takes_lunch' => $e->takes_lunch,
                            'transport_route_id' => $e->transport_route_id,
                            'bus_id' => $e->bus_id,
                        ]);
                        $new->activities()->sync($e->activities->pluck('id')->all());
                        $promoted++;
                    }
                });

            if ($makeCurrent) {
                $this->makeCurrent($to);
            }

            return ['promoted' => $promoted, 'graduated' => $graduated, 'skipped' => $skipped];
        });
    }

    /** Switch the school to this year, starting at Term 1. */
    public function makeCurrent(AcademicYear $year): void
    {
        AcademicYear::query()->update(['is_current' => false]);
        Term::query()->update(['is_current' => false]);

        $year->update(['is_current' => true]);
        Term::where('academic_year_id', $year->id)->where('number', 1)->update(['is_current' => true]);
    }

    private function destination(ClassRoom $c, Collection $byKey): array
    {
        if ((int) $c->gradeLevel->id === (int) $c->branch->final_grade_level_id) {
            return ['graduate', null];
        }

        $next = $byKey->get("{$c->branch_id}-".($c->gradeLevel->level + 1)."-{$c->stream_id}");

        return $next ? ['promote', $next] : ['missing', null];
    }
}
