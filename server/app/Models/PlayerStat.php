<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerStat extends Model
{
    protected $fillable = [
        'player_id',
        'total_points',
        'championships',
        'second_place',
        'third_place',
        'fourth_place',
        'swiss_kings',
        'tournaments_played'
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}
