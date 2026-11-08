<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Learners per class and learners who left, grouped into the school's three sections.
 *
 *  "On roll"  = learners in the class at the END of the period.
 *  "Left"     = learners made Inactive or Transferred DURING the period.
 *
 * Only the current status of each learner is stored, so the roll at the end of a past period is
 * worked out: everyone who has not left, plus anyone who left AFTER the period ended.
 * A learner marked as left with no date (monthly) or no term (termly) cannot be placed in a
 * period, so they are left out of both columns there. They still count in the yearly report.
 */
class LearnerReport
{
    public const SECTIONS = [
        ['name' => 'Early Years Education (EYE)', 'from' => -2, 'to' => 3],   // K1, PP1, PP2, Grade 1 to 3
        ['name' => 'Upper Primary', 'from' => 4, 'to' => 6],                  // Grade 4 to 6
        ['name' => 'Junior School', 'from' => 7, 'to' => 9],                  // Grade 7 to 9
    ];

    private const ZERO = ['boys' => 0, 'girls' => 0, 'total' => 0, 'left_boys' => 0, 'left_girls' => 0, 'left_total' => 0];

    public function build(AcademicYear $year, string $period, ?Term $term, ?int $month, Collection $branches): array
    {
        [$start, $end, $periodLabel] = $this->window($year, $period, $term, $month);

        $termNumbers = Term::where('academic_year_id', $year->id)->pluck('number', 'id');

        $enrollments = Enrollment::query()
            ->join('learners', 'learners.id', '=', 'enrollments.learner_id')
            ->whereNull('learners.deleted_at')
            ->where('enrollments.academic_year_id', $year->id)
            ->whereIn('enrollments.branch_id', $branches->pluck('id'))
            ->get([
                'enrollments.class_id', 'enrollments.status', 'enrollments.status_changed_at',
                'enrollments.status_changed_term_id', 'learners.gender', 'learners.admission_date',
            ]);

        $counts = [];

        foreach ($enrollments as $e) {
            $gone = in_array($e->status, ['inactive', 'transferred'], true);
            $changed = $e->status_changed_at?->toDateString();
            $termNo = $e->status_changed_term_id ? ($termNumbers[$e->status_changed_term_id] ?? null) : null;

            [$onRoll, $left] = match ($period) {
                'yearly' => [! $gone, $gone],
                'termly' => [
                    ! $gone || ($termNo !== null && $termNo > $term->number),
                    $gone && $termNo === $term->number,
                ],
                default => [ // monthly
                    (! $gone || ($changed !== null && $changed > $end))
                        && ($e->admission_date === null || $e->admission_date <= $end),
                    $gone && $changed !== null && $changed >= $start && $changed <= $end,
                ],
            };

            $counts[$e->class_id] ??= self::ZERO;
            $sex = $e->gender === 'male' ? 'boys' : 'girls';

            if ($onRoll) {
                $counts[$e->class_id][$sex]++;
                $counts[$e->class_id]['total']++;
            }
            if ($left) {
                $counts[$e->class_id]['left_'.$sex]++;
                $counts[$e->class_id]['left_total']++;
            }
        }

        $classesByBranch = ClassRoom::query()
            ->with(['gradeLevel:id,name,level', 'stream:id,name'])
            ->whereIn('branch_id', $branches->pluck('id'))
            ->get()
            ->groupBy('branch_id');

        $out = [];
        $grand = self::ZERO;

        foreach ($branches as $branch) {
            $classes = ($classesByBranch[$branch->id] ?? collect())->sortBy([
                fn ($a, $b) => $a->gradeLevel->level <=> $b->gradeLevel->level,
                fn ($a, $b) => $a->stream->name <=> $b->stream->name,
            ]);

            $sections = [];
            $branchTotals = self::ZERO;

            foreach (self::SECTIONS as $section) {
                $rows = [];
                $totals = self::ZERO;

                foreach ($classes->filter(fn ($c) => $c->gradeLevel->level >= $section['from'] && $c->gradeLevel->level <= $section['to']) as $c) {
                    $n = $counts[$c->id] ?? self::ZERO;

                    // Classes with nobody on roll and nobody who left (for example an unused stream) are not listed.
                    if ($n['total'] === 0 && $n['left_total'] === 0) {
                        continue;
                    }

                    $rows[] = ['class' => $c->name] + $n;
                    foreach (self::ZERO as $key => $_) {
                        $totals[$key] += $n[$key];
                    }
                }

                if ($rows) {
                    $sections[] = ['name' => $section['name'], 'rows' => $rows, 'totals' => $totals];
                    foreach (self::ZERO as $key => $_) {
                        $branchTotals[$key] += $totals[$key];
                    }
                }
            }

            foreach (self::ZERO as $key => $_) {
                $grand[$key] += $branchTotals[$key];
            }

            $out[] = ['id' => $branch->id, 'name' => $branch->name, 'sections' => $sections, 'totals' => $branchTotals];
        }

        return [
            'period_label' => $periodLabel,
            'branches' => $out,
            'grand' => $grand,
            'show_grand' => count($out) > 1,
        ];
    }

    /** @return array{0: string, 1: string, 2: string} start date, end date and a printable label */
    private function window(AcademicYear $year, string $period, ?Term $term, ?int $month): array
    {
        if ($period === 'yearly') {
            return ['', '', "Academic year {$year->name}"];
        }

        if ($period === 'termly') {
            if (! $term) {
                throw ValidationException::withMessages(['term_id' => 'Choose a term.']);
            }

            return ['', '', "Term {$term->number}, {$year->name}"];
        }

        // Monthly: the academic year is read as the calendar year it is named after.
        if (! ctype_digit($year->name)) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Monthly reports need an academic year named after its calendar year, such as 2026.',
            ]);
        }

        $first = Carbon::create((int) $year->name, $month ?: 1, 1);

        return [$first->toDateString(), $first->copy()->endOfMonth()->toDateString(), $first->format('F Y')];
    }
}
