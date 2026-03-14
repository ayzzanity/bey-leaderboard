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
        Schema::create('player_stats', function (Blueprint $table) {

            $table->id();

            $table->foreignId('player_id')->constrained();

            $table->integer('total_points')->default(0);

            $table->integer('championships')->default(0);
            $table->integer('second_place')->default(0);
            $table->integer('third_place')->default(0);
            $table->integer('fourth_place')->default(0);

            $table->integer('swiss_kings')->default(0);

            $table->integer('tournaments_played')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
