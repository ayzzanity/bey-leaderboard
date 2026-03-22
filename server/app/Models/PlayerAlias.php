<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerAlias extends Model
{
    protected $fillable = [
        'player_id',
        'alias_name',
        'alias_key',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}
