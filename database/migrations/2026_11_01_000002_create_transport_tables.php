<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('number_plate')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('transport_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('driver_name');
            $table->string('assistant_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
        Schema::dropIfExists('transport_routes');
    }
};
