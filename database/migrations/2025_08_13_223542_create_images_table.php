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
        Schema::create('images', function (Blueprint $table) {
            $table->id();            
            $table->string('url');
            $table->string('audio_url');                      
            $table->string('description')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->foreignId('level_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('dia');
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
