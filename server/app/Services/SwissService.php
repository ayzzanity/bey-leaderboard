<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\TournamentPlayer;

class SwissService
{
    public function calculate($tournamentId)
    {
        $players = TournamentPlayer::where('tournament_id', $tournamentId)->get();

        $matches = MatchResult::where('tournament_id', $tournamentId)
            ->where('stage', 'swiss')
            ->get();

        // Step 1: calculate wins and losses
        foreach ($players as $player) {

            $wins = $matches->where('winner_id', $player->player_id)->count();

            $played = $matches->filter(function ($match) use ($player) {
                return $match->player1_id == $player->player_id ||
                    $match->player2_id == $player->player_id;
            })->count();

            $losses = $played - $wins;

            $player->swiss_wins = $wins;
            $player->swiss_losses = $losses;
            $player->save();
        }

        // Step 2: calculate buchholz
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

                $opponent = $players->firstWhere('player_id', $opponentId);

                if ($opponent) {
                    $buchholz += $opponent->swiss_wins;
                }
            }

            $player->buchholz_score = $buchholz;
            $player->save();
        }

        // Step 3: ranking
        $ranked = TournamentPlayer::where('tournament_id', $tournamentId)
            ->orderByDesc('swiss_wins')
            ->orderByDesc('buchholz_score')
            ->get();

        $rank = 1;

        foreach ($ranked as $player) {

            $player->swiss_rank = $rank;
            $player->save();

            $rank++;
        }
    }
}
