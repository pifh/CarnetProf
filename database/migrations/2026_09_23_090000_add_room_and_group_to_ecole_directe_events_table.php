<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecole_directe_events', function (Blueprint $table) {
            $table->string('room')->nullable()->after('title');
            $table->string('group_name')->nullable()->after('room');
        });
    }

    public function down(): void
    {
        Schema::table('ecole_directe_events', function (Blueprint $table) {
            $table->dropColumn(['room', 'group_name']);
        });
    }
};
