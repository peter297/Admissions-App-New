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
        foreach (range(1, 9) as $n) {
            GradeLevel::firstOrCreate(['level' => $n], ['name' => "Grade $n"]);
        }

        foreach (['Blue', 'Green', 'Red'] as $name) {
            Stream::firstOrCreate(['name' => $name]);
        }

        $grade9 = GradeLevel::where('level', 9)->first();
        $grade6 = GradeLevel::where('level', 6)->first();

        $branches = [
            ['name' => 'Juja Road', 'code' => 'JR', 'final' => $grade9],
            ['name' => 'Kitisuru',  'code' => 'KT', 'final' => $grade6],
            ['name' => 'South C',   'code' => 'SC', 'final' => $grade9],
        ];

        foreach ($branches as $b) {
            $branch = Branch::firstOrCreate(
                ['code' => $b['code']],
                ['name' => $b['name'], 'final_grade_level_id' => $b['final']->id]
            );

            // Generate every class up to the branch's final grade, across all streams.
            $grades  = GradeLevel::where('level', '<=', $b['final']->level)->get();
            $streams = Stream::all();

            foreach ($grades as $grade) {
                foreach ($streams as $stream) {
                    ClassRoom::firstOrCreate([
                        'branch_id'      => $branch->id,
                        'grade_level_id' => $grade->id,
                        'stream_id'      => $stream->id,
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
