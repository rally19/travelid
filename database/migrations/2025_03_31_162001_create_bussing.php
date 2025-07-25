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
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // unique code or custom id
            $table->string('name');
            $table->string('plate_number')->unique();
            $table->enum('status', ['unknown', 'operational', 'maintenance', 'unavailable'])->default('unknown');
            $table->text('description')->nullable();
            $table->string('thumbnail_pic')->nullable();
            $table->string('details_pic')->nullable();
            $table->integer('capacity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
