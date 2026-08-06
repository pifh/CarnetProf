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
        DB::table('appreciations')->where('type', 'disciplinary')->delete();

        // Add the new (type-less) unique index before dropping the old one:
        // the old index is the only thing covering the student_id foreign
        // key right now, so MySQL refuses to drop it until another covering
        // index exists — same lesson learned on discipline_resets.
        Schema::table('appreciations', function (Blueprint $table) {
            $table->unique(['student_id', 'school_class_id', 'subject_id', 'term_id'], 'appreciation_unique_v2');
        });

        Schema::table('appreciations', function (Blueprint $table) {
            $table->dropUnique('appreciation_unique');
            $table->dropColumn('type');
        });

        Schema::table('appreciations', function (Blueprint $table) {
            $table->foreignId('term_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appreciations', function (Blueprint $table) {
            $table->foreignId('term_id')->nullable(false)->change();
        });

        Schema::table('appreciations', function (Blueprint $table) {
            $table->enum('type', ['disciplinary', 'general'])->default('general')->after('subject_id');
        });

        Schema::table('appreciations', function (Blueprint $table) {
            $table->unique(['student_id', 'school_class_id', 'subject_id', 'term_id', 'type'], 'appreciation_unique');
        });

        Schema::table('appreciations', function (Blueprint $table) {
            $table->dropUnique('appreciation_unique_v2');
        });
    }
};
