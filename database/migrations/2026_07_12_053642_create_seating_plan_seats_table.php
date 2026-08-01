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
        Schema::create('seating_plan_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seating_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('desk_index');
            $table->unsignedTinyInteger('seat_index');
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['seating_plan_id', 'desk_index', 'seat_index'], 'seating_plan_seat_position_unique');
            $table->unique(['seating_plan_id', 'student_id'], 'seating_plan_student_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seating_plan_seats');
    }
};
