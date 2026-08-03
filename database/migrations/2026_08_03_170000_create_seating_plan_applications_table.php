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
        Schema::create('seating_plan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seating_plan_id')->constrained('seating_plans')->cascadeOnDelete();
            $table->date('effective_date')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        // One application per existing plan, carrying over its effective_date/is_archived.
        $applicationIdByPlanId = [];
        foreach (DB::table('seating_plans')->get() as $plan) {
            $applicationIdByPlanId[$plan->id] = DB::table('seating_plan_applications')->insertGetId([
                'user_id' => $plan->user_id,
                'seating_plan_id' => $plan->id,
                'effective_date' => $plan->effective_date,
                'is_archived' => $plan->is_archived,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // seating_plan_seats is rebuilt from scratch rather than altered in
        // place: MariaDB ties its two composite unique indexes to three
        // different foreign keys in ways that make them impossible to drop
        // piecemeal, and SQLite (used in tests) doesn't support ALTER TABLE
        // ... DROP FOREIGN KEY at all. A create-copy-rename sidesteps both.
        // student_id becomes nullable here too, for the per-application
        // "block this empty seat" feature (a seat with no student).
        Schema::create('seating_plan_seats_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seating_plan_application_id')->constrained('seating_plan_applications')->cascadeOnDelete();
            $table->foreignId('seating_plan_desk_id')->constrained('seating_plan_desks')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat_index');
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();

            $table->unique(['seating_plan_application_id', 'student_id'], 'seating_plan_app_student_unique');
            $table->unique(['seating_plan_application_id', 'seating_plan_desk_id', 'seat_index'], 'seating_plan_app_seat_position_unique');
        });

        foreach (DB::table('seating_plan_seats')->get() as $seat) {
            DB::table('seating_plan_seats_new')->insert([
                'id' => $seat->id,
                'seating_plan_application_id' => $applicationIdByPlanId[$seat->seating_plan_id],
                'seating_plan_desk_id' => $seat->seating_plan_desk_id,
                'seat_index' => $seat->seat_index,
                'student_id' => $seat->student_id,
                'is_locked' => $seat->is_locked,
                'is_blocked' => false,
                'created_at' => $seat->created_at,
                'updated_at' => $seat->updated_at,
            ]);
        }

        Schema::drop('seating_plan_seats');
        Schema::rename('seating_plan_seats_new', 'seating_plan_seats');

        Schema::table('seating_plans', function (Blueprint $table) {
            $table->dropColumn(['effective_date', 'is_archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_plans', function (Blueprint $table) {
            $table->date('effective_date')->nullable();
            $table->boolean('is_archived')->default(false);
        });

        foreach (DB::table('seating_plan_applications')->orderBy('id')->get() as $application) {
            DB::table('seating_plans')->where('id', $application->seating_plan_id)->update([
                'effective_date' => $application->effective_date,
                'is_archived' => $application->is_archived,
            ]);
        }

        Schema::create('seating_plan_seats_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seating_plan_id')->constrained('seating_plans')->cascadeOnDelete();
            $table->foreignId('seating_plan_desk_id')->constrained('seating_plan_desks')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat_index');
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['seating_plan_id', 'student_id'], 'seating_plan_student_unique');
            $table->unique(['seating_plan_desk_id', 'seat_index'], 'seating_plan_seat_position_unique');
        });

        $planIdByApplicationId = DB::table('seating_plan_applications')->pluck('seating_plan_id', 'id');

        foreach (DB::table('seating_plan_seats')->whereNotNull('student_id')->get() as $seat) {
            DB::table('seating_plan_seats_old')->insert([
                'id' => $seat->id,
                'seating_plan_id' => $planIdByApplicationId[$seat->seating_plan_application_id],
                'seating_plan_desk_id' => $seat->seating_plan_desk_id,
                'seat_index' => $seat->seat_index,
                'student_id' => $seat->student_id,
                'is_locked' => $seat->is_locked,
                'created_at' => $seat->created_at,
                'updated_at' => $seat->updated_at,
            ]);
        }

        Schema::drop('seating_plan_seats');
        Schema::rename('seating_plan_seats_old', 'seating_plan_seats');

        Schema::dropIfExists('seating_plan_applications');
    }
};
