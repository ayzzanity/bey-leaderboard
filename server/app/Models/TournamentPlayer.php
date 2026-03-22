<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentPlayer extends Model
{
    protected $fillable = [
        'tournament_id',
        'player_id',
        'imported_name',
        'seed',
        'final_rank',
        'swiss_wins',
        'swiss_losses',
        'swiss_rank',
        'buchholz_score',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}
