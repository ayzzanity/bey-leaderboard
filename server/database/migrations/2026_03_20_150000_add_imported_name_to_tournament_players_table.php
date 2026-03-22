<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->string('imported_name')->nullable()->after('player_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropColumn('imported_name');
        });
    }
};
