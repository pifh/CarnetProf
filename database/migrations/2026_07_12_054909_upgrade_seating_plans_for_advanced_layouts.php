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
        Schema::table('seating_plans', function (Blueprint $table) {
            // MariaDB refuses to drop the unique index while it backs the FK; add a
            // plain index first so the foreign key constraint stays supported.
            $table->index('school_class_id', 'seating_plans_school_class_id_index');
        });

        Schema::table('seating_plans', function (Blueprint $table) {
            $table->dropUnique(['school_class_id']);
            $table->string('name')->nullable()->after('school_class_id');
        });

        DB::table('seating_plans')->update(['name' => 'Plan de classe']);

        Schema::create('seating_plan_desks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seating_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position_row');
            $table->unsignedSmallInteger('position_col');
            $table->unsignedTinyInteger('capacity')->default(2);
            $table->timestamps();

            $table->unique(['seating_plan_id', 'position_row', 'position_col'], 'seating_plan_desk_position_unique');
        });

        Schema::table('seating_plan_seats', function (Blueprint $table) {
            $table->foreignId('seating_plan_desk_id')
                ->nullable()
                ->after('seating_plan_id')
                ->constrained('seating_plan_desks')
                ->cascadeOnDelete();
        });

        // Turn each plan's implicit, index-based desks into explicit, positioned desks on a single row.
        foreach (DB::table('seating_plans')->get() as $plan) {
            $deskIds = [];

            for ($index = 0; $index < $plan->desks_count; $index++) {
                $deskIds[$index] = DB::table('seating_plan_desks')->insertGetId([
                    'seating_plan_id' => $plan->id,
                    'position_row' => 0,
                    'position_col' => $index,
                    'capacity' => $plan->desk_capacity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (DB::table('seating_plan_seats')->where('seating_plan_id', $plan->id)->get() as $seat) {
                if (isset($deskIds[$seat->desk_index])) {
                    DB::table('seating_plan_seats')->where('id', $seat->id)->update([
                        'seating_plan_desk_id' => $deskIds[$seat->desk_index],
                    ]);
                }
            }
        }

        Schema::table('seating_plan_seats', function (Blueprint $table) {
            $table->dropUnique('seating_plan_seat_position_unique');
            $table->dropColumn('desk_index');
            $table->unique(['seating_plan_desk_id', 'seat_index'], 'seating_plan_seat_position_unique');
        });

        Schema::table('seating_plans', function (Blueprint $table) {
            $table->unique(['school_class_id', 'name']);
            $table->dropColumn(['desk_capacity', 'desks_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_plans', function (Blueprint $table) {
            $table->dropUnique(['school_class_id', 'name']);
            $table->unsignedTinyInteger('desk_capacity')->default(2);
            $table->unsignedSmallInteger('desks_count')->default(1);
        });

        Schema::table('seating_plan_seats', function (Blueprint $table) {
            $table->dropUnique('seating_plan_seat_position_unique');
            $table->unsignedSmallInteger('desk_index')->default(0);
        });

        Schema::table('seating_plan_seats', function (Blueprint $table) {
            $table->unique(['seating_plan_id', 'desk_index', 'seat_index'], 'seating_plan_seat_position_unique');
            $table->dropConstrainedForeignId('seating_plan_desk_id');
        });

        Schema::dropIfExists('seating_plan_desks');

        Schema::table('seating_plans', function (Blueprint $table) {
            $table->unique('school_class_id');
            $table->dropColumn('name');
        });
    }
};
