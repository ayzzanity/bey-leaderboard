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
        $pointsScored = [];
        $pointsAllowed = [];
        $opponents = [];

        foreach ($matches as $match) {
            $winnerId = $match->winner_id;
            $player1Id = $match->player1_id;
            $player2Id = $match->player2_id;

            if ($winnerId !== null) {
                $wins[$winnerId] = ($wins[$winnerId] ?? 0) + 1;
            }

            if ($player1Id !== null) {
                $played[$player1Id] = ($played[$player1Id] ?? 0) + 1;
                $pointsScored[$player1Id] = ($pointsScored[$player1Id] ?? 0) + ($match->player1_score ?? 0);
                $pointsAllowed[$player1Id] = ($pointsAllowed[$player1Id] ?? 0) + ($match->player2_score ?? 0);
            }

            if ($player2Id !== null) {
                $played[$player2Id] = ($played[$player2Id] ?? 0) + 1;
                $pointsScored[$player2Id] = ($pointsScored[$player2Id] ?? 0) + ($match->player2_score ?? 0);
                $pointsAllowed[$player2Id] = ($pointsAllowed[$player2Id] ?? 0) + ($match->player1_score ?? 0);
            }

            if ($player1Id !== null && $player2Id !== null) {
                $opponents[$player1Id][] = $player2Id;
                $opponents[$player2Id][] = $player1Id;
            }
        }

        foreach ($players as $player) {
            $playerWins = $wins[$player->player_id] ?? 0;
            $playerPlayed = $played[$player->player_id] ?? 0;

            $player->swiss_wins = $playerWins;
            $player->swiss_losses = $playerPlayed - $playerWins;
        }

        $tbScores = [];

        foreach ($players as $player) {
            $opponentScores = collect($opponents[$player->player_id] ?? [])
                ->map(fn ($opponentId) => $wins[$opponentId] ?? 0)
                ->sort()
                ->values();

            if ($opponentScores->count() > 2) {
                $opponentScores = $opponentScores->slice(1, $opponentScores->count() - 2)->values();
            }

            $player->buchholz_score = $opponentScores->sum();
            $tbScores[$player->player_id] = $this->calculateTieBreaker($player->player_id, $matches, $players);
        }

        $ranked = $players->sort(function ($left, $right) use ($pointsScored, $tbScores) {
            $leftScore = (float) ($left->swiss_wins ?? 0);
            $rightScore = (float) ($right->swiss_wins ?? 0);
            $leftTb = $tbScores[$left->player_id] ?? 0;
            $rightTb = $tbScores[$right->player_id] ?? 0;
            $leftPts = $pointsScored[$left->player_id] ?? 0;
            $rightPts = $pointsScored[$right->player_id] ?? 0;

            return [
                $rightScore,
                $rightTb,
                $rightPts,
                $right->buchholz_score,
                $left->seed ?? PHP_INT_MAX,
                $left->player_id,
            ] <=> [
                $leftScore,
                $leftTb,
                $leftPts,
                $left->buchholz_score,
                $right->seed ?? PHP_INT_MAX,
                $right->player_id,
            ];
        })->values();

        $rank = 1;

        foreach ($ranked as $player) {
            $player->swiss_rank = $rank;
            $player->save();
            $rank++;
        }
    }

    private function calculateTieBreaker(int $playerId, $matches, $players): int
    {
        $player = $players[$playerId] ?? null;

        if ($player === null) {
            return 0;
        }

        $tbWins = 0;

        foreach ($matches as $match) {
            $isParticipant = $match->player1_id === $playerId || $match->player2_id === $playerId;
            if (!$isParticipant || $match->winner_id !== $playerId) {
                continue;
            }

            $opponentId = $match->player1_id === $playerId ? $match->player2_id : $match->player1_id;
            $opponent = $opponentId !== null ? ($players[$opponentId] ?? null) : null;

            if ($opponent === null) {
                continue;
            }

            if (
                (int) $opponent->swiss_wins === (int) $player->swiss_wins &&
                (int) $opponent->swiss_losses === (int) $player->swiss_losses
            ) {
                $tbWins++;
            }
        }

        return $tbWins;
    }
}
