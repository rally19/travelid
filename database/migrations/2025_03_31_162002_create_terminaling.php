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
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // unique code or custom id
            $table->enum('status', ['unknown', 'operational', 'maintenance', 'unavailable'])->default('unknown');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 25)->nullable();
            $table->text('address')->nullable();
            $table->string('regencity');
            $table->string('province');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
