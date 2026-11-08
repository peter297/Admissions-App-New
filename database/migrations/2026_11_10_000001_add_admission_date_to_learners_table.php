<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            // The day the learner first joined the school. Nullable, because existing learners
            // from the old system have no recorded date until you import or edit it.
            $table->date('admission_date')->nullable()->after('admission_number');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('admission_date');
        });
    }
};
