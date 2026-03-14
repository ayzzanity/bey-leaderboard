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
        Schema::create('tournament_players', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_id')->constrained();
            $table->foreignId('player_id')->constrained();

            $table->integer('seed')->nullable();

            $table->integer('swiss_wins')->default(0);
            $table->integer('swiss_losses')->default(0);

            $table->integer('swiss_rank')->nullable();
            $table->integer('buchholz_score')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_players');
    }
};
