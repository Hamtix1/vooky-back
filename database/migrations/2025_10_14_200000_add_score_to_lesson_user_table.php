<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_user', function (Blueprint $table) {
            $table->integer('score')->default(0)->after('lesson_id');
            $table->integer('correct_answers')->default(0)->after('score');
            $table->integer('total_questions')->default(0)->after('correct_answers');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_user', function (Blueprint $table) {
            $table->dropColumn(['score', 'correct_answers', 'total_questions']);
        });
    }
};
