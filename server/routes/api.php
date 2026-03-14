<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\TournamentImportController;
use App\Http\Controllers\LeaderboardController;

use App\Models\Tournament;
use App\Models\TournamentPlayer;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/admin/import-tournament', [TournamentImportController::class, 'import']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);


Route::get('/tournaments', function () {
    return Tournament::latest()->get();
});

Route::get('/tournaments/{id}', function ($id) {

    return Tournament::with([
        'results.player',
        'players.player'
    ])->findOrFail($id);
});

Route::get('/tournaments/{id}/swiss', function ($id) {

    return TournamentPlayer::with('player')
        ->where('tournament_id', $id)
        ->orderBy('swiss_rank')
        ->get();
});
