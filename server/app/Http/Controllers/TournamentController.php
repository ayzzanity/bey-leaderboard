<?php

namespace App\Http\Controllers;

use App\Models\Tournament;

class TournamentController extends Controller
{
    public function index()
    {
        return Tournament::orderBy('date', 'desc')->get();
    }

    public function show($id)
    {
        return Tournament::with([
            'results.player',
            'players.player'
        ])->findOrFail($id);
    }
}
