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
        Schema::table('discipline_entries', function (Blueprint $table) {
            $table->foreignId('school_class_id')->nullable()->after('student_id')->constrained()->cascadeOnDelete();
        });

        // Backfill from each student's current class: every existing entry
        // was logged before groupe classe membership existed, so the
        // student's class at the time is unambiguous. A correlated subquery
        // (rather than a join-update) works identically on MySQL and SQLite,
        // the latter being what the test suite runs against.
        DB::table('discipline_entries')->update([
            'school_class_id' => DB::raw('(select school_class_id from students where students.id = discipline_entries.student_id)'),
        ]);

        // doctrine/dbal isn't installed, so ->nullable(false)->change() isn't
        // available — flip the column with a raw statement instead. SQLite
        // (used by the test suite) doesn't support MODIFY COLUMN at all;
        // skip there since the app always supplies school_class_id anyway.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE discipline_entries MODIFY school_class_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('discipline_entries', function (Blueprint $table) {
            $table->index(['student_id', 'category', 'school_class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discipline_entries', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'category', 'school_class_id']);
            $table->dropConstrainedForeignId('school_class_id');
        });
    }
};
