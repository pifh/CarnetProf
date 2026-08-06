<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discipline_resets', function (Blueprint $table) {
            $table->foreignId('school_class_id')->nullable()->after('student_id')->constrained()->cascadeOnDelete();
        });

        // Backfill from each student's current class, same rationale (and
        // same portable-subquery approach) as the discipline_entries
        // migration.
        DB::table('discipline_resets')->update([
            'school_class_id' => DB::raw('(select school_class_id from students where students.id = discipline_resets.student_id)'),
        ]);

        // See discipline_entries migration for why this is skipped on SQLite.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE discipline_resets MODIFY school_class_id BIGINT UNSIGNED NOT NULL');
        }

        // Add the new composite unique before dropping the old one: the old
        // ['student_id','category'] index is the only thing covering the
        // student_id foreign key right now, so MySQL refuses to drop it
        // until another covering index exists.
        Schema::table('discipline_resets', function (Blueprint $table) {
            $table->unique(['student_id', 'category', 'school_class_id']);
        });

        Schema::table('discipline_resets', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discipline_resets', function (Blueprint $table) {
            $table->unique(['student_id', 'category']);
        });

        Schema::table('discipline_resets', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'category', 'school_class_id']);
            $table->dropConstrainedForeignId('school_class_id');
        });
    }
};
