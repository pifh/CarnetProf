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
        Schema::table('student_subgroups', function (Blueprint $table) {
            // Nullable: same backward-compat rule as evaluations/appreciations/etc. —
            // only meaningful once a class has 2+ subjects.
            $table->foreignId('subject_id')->nullable()->after('school_class_id')->constrained()->nullOnDelete();
            // Which generator run created this group, if any (null = manually created,
            // e.g. from the student's "Groupes" tab or the resource). nullOnDelete:
            // deleting an archived GroupGeneration must not delete the groups it
            // produced, only detach the lineage.
            $table->foreignId('group_generation_id')->nullable()->after('color')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_subgroups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_generation_id');
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
