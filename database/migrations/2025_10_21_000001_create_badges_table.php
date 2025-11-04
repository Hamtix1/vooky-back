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
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Nombre de la insignia (ej: "Principiante", "Experto")
            $table->text('description')->nullable(); // Descripción de la insignia
            $table->string('image')->nullable(); // Ruta de la imagen de la insignia
            $table->integer('lessons_required')->default(0); // Número de lecciones requeridas
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
