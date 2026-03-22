<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\LeaderboardAdminController;
use App\Http\Controllers\Admin\TournamentImportController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\PlayerController;
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
Route::post('/admin/import-tournament/finalize', [TournamentImportController::class, 'finalize']);
Route::post('/admin/recalculate-leaderboard', [LeaderboardAdminController::class, 'recalculate']);
Route::post('/admin/recalculate-leaderboard/prepare', [LeaderboardAdminController::class, 'prepareRecalculation']);
Route::post('/admin/recalculate-leaderboard/tournaments/{id}', [LeaderboardAdminController::class, 'recalculateTournament']);
Route::post('/admin/recalculate-leaderboard/finalize', [LeaderboardAdminController::class, 'finalizeRecalculation']);
Route::post('/admin/player-corrections', [LeaderboardAdminController::class, 'correctPlayerName']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);
Route::get('/players', [PlayerController::class, 'search']);
Route::get('/players/{id}', [PlayerController::class, 'show']);


Route::get('/tournaments', [TournamentController::class, 'index']);
Route::patch('/tournaments/batch', [TournamentController::class, 'batchUpdate']);

Route::get('/tournaments/{id}', [TournamentController::class, 'show']);
Route::patch('/tournaments/{id}', [TournamentController::class, 'update']);
Route::delete('/tournaments/{id}', [TournamentController::class, 'destroy']);
