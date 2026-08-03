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
        Schema::table('seating_plans', function (Blueprint $table) {
            $table->date('effective_date')->nullable()->after('name');
            $table->boolean('is_archived')->default(false)->after('effective_date');
            // 'left'|'center'|'right', null means no teacher's desk shown.
            $table->string('teacher_desk_position')->nullable()->after('is_archived');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_plans', function (Blueprint $table) {
            $table->dropColumn(['effective_date', 'is_archived', 'teacher_desk_position']);
        });
    }
};
