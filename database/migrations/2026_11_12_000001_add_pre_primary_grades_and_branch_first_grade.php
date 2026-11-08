<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grade levels are numbered so that "next grade = level + 1" drives promotion.
        // K1, PP1 and PP2 come before Grade 1, so they take the levels -2, -1 and 0,
        // which needs a signed column. Grade 1 to 9 keep their numbers, so rules such as
        // "assessment number required from Grade 4" are unaffected.
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->tinyInteger('level')->change();
        });

        foreach ([-2 => 'K1', -1 => 'PP1', 0 => 'PP2'] as $level => $name) {
            DB::table('grade_levels')->updateOrInsert(
                ['level' => $level],
                ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Each branch now has a first grade as well as a final grade.
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('first_grade_level_id')->nullable()->after('code')->constrained('grade_levels');
        });

        // Every existing branch starts at Grade 1, exactly as it works today.
        $gradeOne = DB::table('grade_levels')->where('level', 1)->value('id');
        DB::table('branches')->update(['first_grade_level_id' => $gradeOne]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('first_grade_level_id');
        });

        DB::table('grade_levels')->whereIn('level', [-2, -1, 0])->delete();
    }
};
