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
        Schema::table('appreciations', function (Blueprint $table) {
            // MySQL relies on appreciation_unique to satisfy the student_id foreign
            // key; give it a standalone index first so the unique index can be dropped.
            $table->index('student_id', 'appreciations_student_id_index');
            $table->dropUnique('appreciation_unique');
            // Nullable: a class with no subject (or a single implicit one) doesn't need
            // to pick one. Required only in the page once a class has 2+ subjects.
            $table->foreignId('subject_id')->nullable()->after('school_class_id')->constrained()->nullOnDelete();
            $table->unique(['student_id', 'school_class_id', 'subject_id', 'term_id', 'type'], 'appreciation_unique');
            $table->dropIndex('appreciations_student_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appreciations', function (Blueprint $table) {
            $table->index('student_id', 'appreciations_student_id_index');
            $table->dropUnique('appreciation_unique');
            $table->dropConstrainedForeignId('subject_id');
            $table->unique(['student_id', 'school_class_id', 'term_id', 'type'], 'appreciation_unique');
            $table->dropIndex('appreciations_student_id_index');
        });
    }
};
