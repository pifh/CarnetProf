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
        Schema::table('users', function (Blueprint $table) {
            $table->text('ecole_directe_ics_url')->nullable()->after('avatar');
            $table->timestamp('ecole_directe_synced_at')->nullable()->after('ecole_directe_ics_url');
            $table->json('calendar_feed_categories')->nullable()->after('ecole_directe_synced_at');
            $table->string('calendar_token', 64)->nullable()->unique()->after('calendar_feed_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ecole_directe_ics_url', 'ecole_directe_synced_at', 'calendar_feed_categories', 'calendar_token']);
        });
    }
};
