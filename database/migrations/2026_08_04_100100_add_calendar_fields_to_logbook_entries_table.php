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
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->string('status')->default('done')->after('date');
            $table->foreignId('ecole_directe_event_id')
                ->nullable()
                ->unique()
                ->after('progression_sequence_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('logbook_entries')->whereNull('content')->update(['content' => '']);

        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
        });

        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ecole_directe_event_id');
            $table->dropColumn('status');
        });
    }
};
