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
        Schema::create('school_class_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_class_id', 'subject_id']);
        });

        // Carry forward each class's existing single subject (if any) into the pivot.
        DB::table('school_classes')
            ->whereNotNull('subject_id')
            ->get(['id', 'subject_id'])
            ->each(function ($schoolClass) {
                DB::table('school_class_subject')->insert([
                    'school_class_id' => $schoolClass->id,
                    'subject_id' => $schoolClass->subject_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Best-effort: restore the first linked subject per class (lossy if a class had more than one).
        DB::table('school_class_subject')
            ->orderBy('id')
            ->get(['school_class_id', 'subject_id'])
            ->unique('school_class_id')
            ->each(function ($pivot) {
                DB::table('school_classes')
                    ->where('id', $pivot->school_class_id)
                    ->update(['subject_id' => $pivot->subject_id]);
            });

        Schema::dropIfExists('school_class_subject');
    }
};
