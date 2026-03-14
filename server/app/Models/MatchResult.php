<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchResult extends Model
{
    protected $fillable = [
        'tournament_id',
        'player1_id',
        'player2_id',
        'winner_id',
        'round',
        'stage'
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player1()
    {
        return $this->belongsTo(Player::class);
    }

    public function player2()
    {
        return $this->belongsTo(Player::class);
    }

    public function winner()
    {
        return $this->belongsTo(Player::class);
    }
}
