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
        Schema::table('seating_plan_desks', function (Blueprint $table) {
            // A blocked "desk" is a reserved empty slot (e.g. a pillar, a
            // permanently-unused corner): it occupies grid space and counts
            // toward column numbering, but is never assignable to a student
            // and isn't rendered as a normal desk.
            $table->boolean('is_blocked')->default(false)->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_plan_desks', function (Blueprint $table) {
            $table->dropColumn('is_blocked');
        });
    }
};
