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
        Schema::create('group_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('mode', ['count', 'size']);
            $table->unsignedInteger('group_count')->nullable();
            $table->unsignedInteger('group_size')->nullable();
            // Immutable snapshot of the generated groups at save time: array<int, int[]>
            // (group index => student ids), index-aligned with the StudentSubgroup rows
            // created for this run. Kept even if those rows are later edited or deleted,
            // so the archive stays a true historical record and anti-repeat scoring
            // keeps working.
            $table->json('groups');
            // Records the settings used for this run (level/gender mode, avoid-repeats,
            // excluded students, locked placements, keep-together/apart pairs, any
            // unresolved conflicts) so the history section can summarize each past run.
            $table->json('criteria');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_generations');
    }
};
