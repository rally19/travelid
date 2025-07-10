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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // unique code or custom id
            $table->foreignId('users_id')->constrained('users');
            $table->enum('status', ['pending', 'success', 'cancelled', 'failed'])->default('pending');
            $table->string('bus_code')->nullable();
            $table->string('bus_name')->nullable();
            $table->string('routes_schedules_code')->nullable();
            $table->string('route_name')->nullable();
            $table->string('departure_terminal')->nullable();
            $table->string('departure_location')->nullable();
            $table->dateTime('departure_time')->nullable();
            $table->string('arrival_terminal')->nullable();
            $table->string('arrival_location')->nullable();
            $table->dateTime('arrival_time')->nullable();
            $table->string('payment_proof')->nullable();
            $table->string('payment_method')->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('orders_seats', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // unique code or custom id
            $table->foreignId('orders_id')->constrained('orders');
            $table->foreignId('routes_schedules_id')->constrained('routes_schedules');
            $table->string('name');
            $table->integer('age');
            $table->enum('title', ['Mx', 'Ms', 'Mrs', 'Mr'])->default('Mx');
            $table->decimal('cost', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders_seats');
        Schema::dropIfExists('orders');
    }
};
