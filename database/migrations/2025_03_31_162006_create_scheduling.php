<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('routes_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // unique code or custom id can be set manually
            $table->foreignId('buses_id')->nullable()->constrained('buses');
            $table->string('name');
            $table->enum('status', ['unknown', 'unavailable', 'operational'])->default('unknown');
            $table->decimal('price', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->foreignId('departure_id')->nullable()->constrained('terminals');
            $table->dateTime('departure_time')->nullable();
            $table->foreignId('arrival_id')->nullable()->constrained('terminals');
            $table->dateTime('arrival_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes_schedules');
    }
};
