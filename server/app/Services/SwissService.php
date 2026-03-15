<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\TournamentPlayer;

class SwissService
{
    public function calculate($tournamentId)
    {
        $players = TournamentPlayer::where('tournament_id', $tournamentId)->get()->keyBy('player_id');

        $matches = MatchResult::where('tournament_id', $tournamentId)
            ->where('stage', 'swiss')
            ->whereNotNull('winner_id')
            ->get();

        $wins = [];
        $played = [];

        // Step 1: calculate wins and matches played
        foreach ($matches as $match) {

            $wins[$match->winner_id] = ($wins[$match->winner_id] ?? 0) + 1;

            $played[$match->player1_id] = ($played[$match->player1_id] ?? 0) + 1;
            $played[$match->player2_id] = ($played[$match->player2_id] ?? 0) + 1;
        }

        // Apply win/loss stats
        foreach ($players as $player) {

            $playerWins = $wins[$player->player_id] ?? 0;
            $playerPlayed = $played[$player->player_id] ?? 0;

            $player->swiss_wins = $playerWins;
            $player->swiss_losses = $playerPlayed - $playerWins;
        }

        // Step 2: calculate Buchholz
        foreach ($players as $player) {

            $playerMatches = $matches->filter(function ($match) use ($player) {
                return $match->player1_id == $player->player_id ||
                    $match->player2_id == $player->player_id;
            });

            $buchholz = 0;

            foreach ($playerMatches as $match) {

                $opponentId = $match->player1_id == $player->player_id
                    ? $match->player2_id
                    : $match->player1_id;

                if ($opponentId && isset($players[$opponentId])) {
                    $buchholz += $players[$opponentId]->swiss_wins ?? 0;
                }
            }

            $player->buchholz_score = $buchholz;
        }

        // Step 3: ranking
        $ranked = $players->sortByDesc('buchholz_score')
            ->sortByDesc('swiss_wins')
            ->values();

        $rank = 1;

        foreach ($ranked as $player) {

            $player->swiss_rank = $rank;
            $player->save();

            $rank++;
        }
    }
}
