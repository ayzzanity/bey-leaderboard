<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentResult extends Model
{
    protected $fillable = [
        'tournament_id',
        'player_id',
        'placement',
        'points_awarded'
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
