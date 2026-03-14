<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    //
    protected $fillable = [
        'name',
        'challonge_id',
        'challonge_url',
        'date',
        'participants_count'
    ];

    public function results()
    {
        return $this->hasMany(TournamentResult::class);
    }

    public function players()
    {
        return $this->hasMany(TournamentPlayer::class);
    }
}
