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
        Schema::create('student_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            // Snapshot of the class's school year at upload time, so an archived
            // photo can be labelled "from 2025-2026" without cross-referencing.
            $table->string('school_year')->nullable();
            $table->enum('source', ['manual', 'pdf_import'])->default('manual');
            // Only one photo per student is ever "current" — replacing a photo
            // archives the previous one instead of deleting it.
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['student_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_photos');
    }
};
