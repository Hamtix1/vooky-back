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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->onDelete('cascade');
            $table->string('title');
            // slug no es necesario para esta aplicación; lo dejamos nullable por compatibilidad
            $table->string('slug')->nullable();
            // Tipos de lección restringidos a los solicitados por el cliente
            $table->enum('content_type', ['Combinado', 'Enlace de categorías', 'Mixto']);
            // Día de la lección: debe ser un entero positivo y no exceder el número máximo de día de las imágenes del nivel
            $table->integer('dia')->unsigned()->nullable();
            // Si se requiere orden, se puede conservar; lo mantenemos por compatibilidad
            $table->integer('order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
