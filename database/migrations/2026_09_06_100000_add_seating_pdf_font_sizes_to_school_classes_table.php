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
        Schema::table('school_classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('seating_pdf_first_name_font_size')->nullable()->after('color');
            $table->unsignedTinyInteger('seating_pdf_last_name_font_size')->nullable()->after('seating_pdf_first_name_font_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn(['seating_pdf_first_name_font_size', 'seating_pdf_last_name_font_size']);
        });
    }
};
