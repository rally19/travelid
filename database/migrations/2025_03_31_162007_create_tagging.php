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
        Schema::create('types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('types_id')->constrained('types')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('tags_cons', function (Blueprint $table) {
            $table->foreignId('buses_id')->constrained('buses')->onDelete('cascade');
            $table->foreignId('tags_id')->constrained('tags')->onDelete('cascade');
            $table->primary(['buses_id', 'tags_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags_cons');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('types');
    }
};
