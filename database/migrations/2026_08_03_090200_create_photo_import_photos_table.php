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
        Schema::create('photo_import_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('path');
            // Filled in once the teacher confirms the assignment for this photo;
            // stays null for photos left as "Ignorer".
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_import_photos');
    }
};
