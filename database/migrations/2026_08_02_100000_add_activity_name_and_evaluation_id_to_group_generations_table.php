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
        Schema::table('group_generations', function (Blueprint $table) {
            // Groups are made for one particular activity (an exposé, a TP...), not
            // for a whole term — the generated StudentSubgroups are named after it.
            $table->string('activity_name')->nullable()->after('subject_id');
            // Set only when the teacher marks the activity as graded: the same score
            // is duplicated to every member of a group via this Evaluation's Grades.
            // nullOnDelete: deleting the archive entry must not delete real grades.
            $table->foreignId('evaluation_id')->nullable()->after('term_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_id');
            $table->dropColumn('activity_name');
        });
    }
};
