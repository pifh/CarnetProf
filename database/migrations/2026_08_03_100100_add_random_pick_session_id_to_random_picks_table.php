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
        Schema::table('random_picks', function (Blueprint $table) {
            $table->foreignId('random_pick_session_id')->nullable()->after('school_class_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('random_picks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('random_pick_session_id');
        });
    }
};
