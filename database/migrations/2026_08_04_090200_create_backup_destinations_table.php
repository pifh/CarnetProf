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
        Schema::create('backup_destinations', function (Blueprint $table) {
            $table->id();
            // Null user_id means this is a site-wide destination, managed by
            // admins — unlike login_logs' nullable user_id (which means
            // "unknown/deleted user"), null here is a deliberate, permanent
            // state, not a fallback.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('label');
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_destinations');
    }
};
