<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();   // grade applied for

            $table->string('full_name');
            $table->string('assessment_number')->nullable();      // required for Grade 4-9, enforced in the form request
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female'])->nullable();

            $table->string('parent_name');
            $table->enum('parent_type', ['mother', 'father', 'guardian'])->default('mother');
            $table->string('parent_phone');

            $table->string('mpesa_code')->nullable()->unique();   // admission fee payment
            $table->enum('result', ['pending', 'passed', 'failed'])->default('pending');
            $table->text('notes')->nullable();

            // Set once promoted; links the applicant to the learner they became.
            $table->foreignId('learner_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('promoted_at')->nullable();

            $table->timestamps();

            $table->index(['academic_year_id', 'branch_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
