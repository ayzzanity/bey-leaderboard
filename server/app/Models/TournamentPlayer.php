<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentPlayer extends Model
{
    protected $fillable = [
        'tournament_id',
        'player_id',
        'final_rank'
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
