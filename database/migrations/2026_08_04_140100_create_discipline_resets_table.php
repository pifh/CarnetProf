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
        Schema::create('discipline_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            // Watermark: the highest discipline_entries.id that existed at
            // reset time. The trip counter then only counts entries with a
            // greater id. An id comparison is exact and monotonic, unlike a
            // timestamp column, which can't tell apart an entry logged just
            // before a reset from one logged just after when both happen
            // within the same whole second.
            $table->unsignedBigInteger('last_entry_id')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discipline_resets');
    }
};
