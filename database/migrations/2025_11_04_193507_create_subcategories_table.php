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
        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre de la subcategoría (ej: "rojo", "grande", "pequeño")
            $table->string('description')->nullable(); // Descripción opcional
            $table->foreignId('category_id')->constrained()->onDelete('cascade'); // Pertenece a una categoría
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcategories');
    }
};
