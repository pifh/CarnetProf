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
        Schema::table('seating_plan_applications', function (Blueprint $table) {
            $table->foreignId('school_class_id')
                ->nullable()
                ->after('seating_plan_id')
                ->constrained('school_classes')
                ->cascadeOnDelete();
        });

        // Backfill from each plan's (still-present) school_class_id — a plan
        // used to belong to exactly one class, so every existing application
        // inherits that class.
        foreach (DB::table('seating_plans')->get(['id', 'school_class_id']) as $plan) {
            DB::table('seating_plan_applications')
                ->where('seating_plan_id', $plan->id)
                ->update(['school_class_id' => $plan->school_class_id]);
        }

        Schema::table('seating_plan_applications', function (Blueprint $table) {
            $table->foreignId('school_class_id')->nullable(false)->change();
        });

        // The composite unique on seating_plans doesn't back any other
        // foreign key (school_class_id already has its own dedicated index
        // from the advanced-layouts migration), so it drops cleanly.
        Schema::table('seating_plans', function (Blueprint $table) {
            $table->dropUnique('seating_plans_school_class_id_name_unique');
        });

        // That dedicated index must also go before the column itself —
        // SQLite's table-recreation strategy for dropColumn() doesn't drop
        // indexes referencing the dropped column on its own.
        Schema::table('seating_plans', function (Blueprint $table) {
            $table->dropIndex('seating_plans_school_class_id_index');
        });

        Schema::table('seating_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_class_id');
        });

        Schema::table('seating_plans', function (Blueprint $table) {
            $table->unique(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_plans', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->foreignId('school_class_id')->nullable()->after('user_id')->constrained('school_classes')->cascadeOnDelete();
        });

        // Best-effort: pulls the class back from each plan's first application.
        $firstApplicationByPlan = DB::table('seating_plan_applications')
            ->orderBy('id')
            ->get(['seating_plan_id', 'school_class_id'])
            ->unique('seating_plan_id');

        foreach ($firstApplicationByPlan as $application) {
            DB::table('seating_plans')->where('id', $application->seating_plan_id)->update([
                'school_class_id' => $application->school_class_id,
            ]);
        }

        Schema::table('seating_plans', function (Blueprint $table) {
            $table->foreignId('school_class_id')->nullable(false)->change();
            $table->unique(['school_class_id', 'name']);
        });

        Schema::table('seating_plan_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_class_id');
        });
    }
};
