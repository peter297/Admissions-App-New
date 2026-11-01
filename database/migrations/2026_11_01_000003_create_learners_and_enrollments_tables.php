<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permanent identity of a learner. Never holds class or status.
        Schema::create('learners', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');                          // as per birth certificate
            $table->string('assessment_number')->nullable()->unique();
            $table->string('admission_number')->unique();
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth');
            $table->string('birth_certificate_number')->nullable();
            $table->enum('nationality', ['citizen', 'non_citizen'])->default('citizen');
            $table->string('kemis_code')->nullable()->unique();   // nullable allows several "not yet issued"
            $table->string('contact_person')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                     // Taekwondo, Soccer
            $table->timestamps();
        });

        // One row per learner per academic year. This is what makes history work.
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('branch_id')->constrained();        // denormalised from class for fast scoping

            $table->enum('status', ['active', 'inactive', 'transferred', 'graduated'])->default('active');
            $table->foreignId('status_changed_term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->date('status_changed_at')->nullable();
            $table->text('status_note')->nullable();

            $table->boolean('takes_lunch')->default(false);
            $table->foreignId('transport_route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // A learner has only one enrollment per year.
            $table->unique(['learner_id', 'academic_year_id']);
            $table->index(['academic_year_id', 'branch_id', 'status']);
            $table->index(['class_id', 'academic_year_id']);
        });

        Schema::create('activity_enrollment', function (Blueprint $table) {
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->primary(['enrollment_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_enrollment');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('learners');
    }
};
