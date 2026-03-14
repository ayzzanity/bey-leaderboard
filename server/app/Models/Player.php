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
}
