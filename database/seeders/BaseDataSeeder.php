<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassRoom;   // maps to the `classes` table (see README)
use App\Models\GradeLevel;
use App\Models\Stream;
use App\Models\Term;
use Illuminate\Database\Seeder;

class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        // Pre-primary comes before Grade 1, so it uses levels -2, -1 and 0.
        $levels = [-2 => 'K1', -1 => 'PP1', 0 => 'PP2'];
        foreach (range(1, 9) as $n) {
            $levels[$n] = "Grade $n";
        }
        foreach ($levels as $level => $name) {
            GradeLevel::firstOrCreate(['level' => $level], ['name' => $name]);
        }

        foreach (['Blue', 'Green', 'Red'] as $name) {
            Stream::firstOrCreate(['name' => $name]);
        }

        $grade = fn (int $level) => GradeLevel::where('level', $level)->first();

        // first / final are grade levels: -2 = K1, 1 = Grade 1, 6 = Grade 6, 9 = Grade 9.
        $branches = [
            ['name' => 'Juja Road', 'code' => 'JR', 'first' => 1,  'final' => 9],
            ['name' => 'Kitisuru',  'code' => 'KT', 'first' => -2, 'final' => 6],
            ['name' => 'South C',   'code' => 'SC', 'first' => -2, 'final' => 9],
        ];

        foreach ($branches as $b) {
            // firstOrCreate: a branch that already exists keeps whatever first and final grade
            // it has now (change those under School settings), so re-running is always safe.
            $branch = Branch::firstOrCreate(
                ['code' => $b['code']],
                [
                    'name' => $b['name'],
                    'first_grade_level_id' => $grade($b['first'])->id,
                    'final_grade_level_id' => $grade($b['final'])->id,
                ]
            );

            // Generate every class from the branch's first grade to its final grade, across all streams.
            $first = GradeLevel::find($branch->first_grade_level_id)?->level ?? 1;
            $final = GradeLevel::find($branch->final_grade_level_id)->level;

            $grades = GradeLevel::whereBetween('level', [$first, $final])->get();
            $streams = Stream::all();

            foreach ($grades as $g) {
                foreach ($streams as $stream) {
                    ClassRoom::firstOrCreate([
                        'branch_id' => $branch->id,
                        'grade_level_id' => $g->id,
                        'stream_id' => $stream->id,
                    ]);
                }
            }
        }

        $year = AcademicYear::firstOrCreate(['name' => '2026'], ['is_current' => true]);
        foreach ([1, 2, 3] as $n) {
            Term::firstOrCreate(['academic_year_id' => $year->id, 'number' => $n]);
        }
    }
}
