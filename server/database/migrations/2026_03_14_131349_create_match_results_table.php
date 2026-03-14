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
        Schema::create('match_results', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tournament_id')->constrained();

            $table->foreignId('player1_id')->nullable();
            $table->foreignId('player2_id')->nullable();

            $table->foreignId('winner_id')->nullable();

            $table->string('stage');

            $table->integer('round');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
