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
            $table->timestamp('consent_accepted_at')->nullable()->after('password');
            $table->string('consent_version')->nullable()->after('consent_accepted_at');

            $table->text('app_authentication_secret')->nullable()->after('consent_version');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $table->boolean('has_email_authentication')->default(false)->after('app_authentication_recovery_codes');

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'consent_accepted_at',
                'consent_version',
                'app_authentication_secret',
                'app_authentication_recovery_codes',
                'has_email_authentication',
            ]);
        });
    }
};
