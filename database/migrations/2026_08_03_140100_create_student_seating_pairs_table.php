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
        Schema::create('student_seating_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_student_id')->constrained('students')->cascadeOnDelete();
            // Which constraint this pair expresses, from the perspective of
            // student_id: sit together, never together, or as far apart as
            // the generator can place them.
            $table->enum('type', ['next_to', 'not_next_to', 'far_from']);
            $table->timestamps();

            $table->unique(['student_id', 'related_student_id', 'type'], 'seating_pair_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_seating_pairs');
    }
};
