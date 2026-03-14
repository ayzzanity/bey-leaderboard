<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlayerStat;

class LeaderboardController extends Controller
{
    public function index()
    {
        return PlayerStat::with('player')
            ->orderByDesc('total_points')
            ->get();
    }
}
