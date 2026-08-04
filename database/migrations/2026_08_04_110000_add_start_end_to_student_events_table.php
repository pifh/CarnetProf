<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_events', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('type');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->boolean('all_day')->default(true)->after('ends_at');
        });

        DB::table('student_events')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('student_events')->where('id', $row->id)->update([
                    'starts_at' => $row->event_date.' 00:00:00',
                ]);
            }
        });

        Schema::table('student_events', function (Blueprint $table) {
            // The old composite index must stay until a new one covering
            // student_id exists, or MySQL refuses to drop it (it backs the
            // student_id foreign key).
            $table->index(['student_id', 'starts_at']);
        });

        Schema::table('student_events', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'event_date']);
            $table->dropColumn('event_date');
        });

        Schema::table('student_events', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_events', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('type');
        });

        DB::table('student_events')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('student_events')->where('id', $row->id)->update([
                    'event_date' => Carbon::parse($row->starts_at)->format('Y-m-d'),
                ]);
            }
        });

        Schema::table('student_events', function (Blueprint $table) {
            // Same as up(): keep an index covering student_id at all times.
            $table->index(['student_id', 'event_date']);
        });

        Schema::table('student_events', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'starts_at']);
            $table->dropColumn(['starts_at', 'ends_at', 'all_day']);
        });

        Schema::table('student_events', function (Blueprint $table) {
            $table->date('event_date')->nullable(false)->change();
        });
    }
};
