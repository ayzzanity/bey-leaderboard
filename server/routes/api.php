<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\TournamentImportController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\LeaderboardController;
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


Route::get('/tournaments', [TournamentController::class, 'index']);

Route::get('/tournaments/{id}', [TournamentController::class, 'show']);
