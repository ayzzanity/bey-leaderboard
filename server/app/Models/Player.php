<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{

    protected $fillable = [
        'name',
        'tag',
        'country'
    ];

    public function stats()
    {
        return $this->hasOne(PlayerStat::class);
    }

    public function aliases()
    {
        return $this->hasMany(PlayerAlias::class);
    }

    public function tournamentPlayers()
    {
        return $this->hasMany(TournamentPlayer::class);
    }

    public function tournamentResults()
    {
        return $this->hasMany(TournamentResult::class);
    }
}
