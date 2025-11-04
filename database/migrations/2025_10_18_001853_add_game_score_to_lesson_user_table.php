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
        Schema::table('lesson_user', function (Blueprint $table) {
            // Agregar game_score (puntuación del juego con combos - sin límite, puede ser miles de puntos)
            $table->integer('game_score')->default(0)->after('score');
            // Renombrar score a accuracy para claridad (porcentaje de aciertos 0-100)
            $table->renameColumn('score', 'accuracy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_user', function (Blueprint $table) {
            $table->dropColumn('game_score');
            $table->renameColumn('accuracy', 'score');
        });
    }
};
