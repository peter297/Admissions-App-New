<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // Grade 1 ... Grade 9
            $table->unsignedTinyInteger('level')->unique(); // 1..9, used for ordering / promotion
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // Juja Road, Kitisuru, South C
            $table->string('code', 10)->unique();
            // The last grade offered. Learners finishing it become alumni.
            $table->foreignId('final_grade_level_id')->constrained('grade_levels');
            $table->timestamps();
        });

        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // Blue, Green, Red
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->foreignId('stream_id')->constrained();
            $table->timestamps();

            $table->unique(['branch_id', 'grade_level_id', 'stream_id']);
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // 2026
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');     // 1, 2, 3
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_year_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('streams');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('grade_levels');
    }
};
