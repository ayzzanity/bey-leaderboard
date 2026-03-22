<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\PlayerStat;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use Illuminate\Support\Collection;

class LeaderboardAggregationService
{
    private const SWISS_KING_POINTS = 10;
    private const POINTS_BY_PLACEMENT = [
        1 => 10,
        2 => 7,
    ];

    public function rebuildTournamentResults(int $tournamentId): void
    {
        $players = TournamentPlayer::query()
            ->where('tournament_id', $tournamentId)
            ->get()
            ->keyBy('player_id');

        if ($players->isEmpty()) {
            return;
        }

        $swissMatches = MatchResult::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'swiss')
            ->whereNotNull('winner_id')
            ->get();

        $singleElimMatches = MatchResult::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'single_elim')
            ->whereNotNull('winner_id')
            ->get();

        $placements = $this->determinePlacements($tournamentId, $players, $swissMatches, $singleElimMatches);

        TournamentResult::query()->where('tournament_id', $tournamentId)->delete();

        foreach ($placements as $playerId => $placement) {
            $player = $players->get($playerId);

            TournamentResult::create([
                'tournament_id' => $tournamentId,
                'player_id' => $playerId,
                'placement' => $placement,
                'points_awarded' => $this->pointsForPlacement(
                    $placement,
                    $singleElimMatches->isNotEmpty(),
                    $swissMatches->isNotEmpty(),
                    $player?->swiss_rank === 1
                ),
            ]);
        }
    }

    public function rebuildPlayerStats(): void
    {
        $singleElimTournamentIds = MatchResult::query()
            ->where('stage', 'single_elim')
            ->whereNotNull('winner_id')
            ->distinct()
            ->pluck('tournament_id')
            ->map(fn ($id) => (int) $id);

        $swissTournamentIds = MatchResult::query()
            ->where('stage', 'swiss')
            ->whereNotNull('winner_id')
            ->distinct()
            ->pluck('tournament_id')
            ->map(fn ($id) => (int) $id);

        $playerIds = TournamentPlayer::query()
            ->distinct()
            ->pluck('player_id');

        PlayerStat::query()->delete();

        foreach ($playerIds as $playerId) {
            $results = TournamentResult::query()
                ->where('player_id', $playerId)
                ->get();

            $singleElimResults = $results->whereIn('tournament_id', $singleElimTournamentIds);

            $swissKings = TournamentPlayer::query()
                ->where('player_id', $playerId)
                ->where('swiss_rank', 1)
                ->whereIn('tournament_id', $swissTournamentIds)
                ->count();

            PlayerStat::create([
                'player_id' => $playerId,
                'total_points' => $results->sum('points_awarded'),
                'championships' => $singleElimResults->where('placement', 1)->count(),
                'second_place' => $singleElimResults->where('placement', 2)->count(),
                'third_place' => $singleElimResults->where('placement', 3)->count(),
                'fourth_place' => $singleElimResults->where('placement', 4)->count(),
                'swiss_kings' => $swissKings,
                'tournaments_played' => TournamentPlayer::query()
                    ->where('player_id', $playerId)
                    ->count(),
            ]);
        }
    }

    private function determinePlacements(int $tournamentId, Collection $players, ?Collection $swissMatches = null, ?Collection $singleElimMatches = null): array
    {
        $swissMatches ??= MatchResult::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'swiss')
            ->whereNotNull('winner_id')
            ->get();

        $placements = [];
        $nextPlacement = 1;

        $singleElimMatches ??= MatchResult::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'single_elim')
            ->whereNotNull('winner_id')
            ->get();

        if ($singleElimMatches->isEmpty() && $swissMatches->isNotEmpty()) {
            $swissKing = $players
                ->sortBy(fn ($player) => $this->placementSortKey($player))
                ->first();

            return $swissKing !== null ? [$swissKing->player_id => 1] : [];
        }

        if ($singleElimMatches->isNotEmpty()) {
            $final = $singleElimMatches
                ->sortByDesc('round')
                ->sortByDesc('id')
                ->first();

            if ($final && $final->winner_id) {
                $placements[$final->winner_id] = $nextPlacement++;

                $runnerUpId = $this->loserForMatch($final);
                if ($runnerUpId !== null) {
                    $placements[$runnerUpId] = $nextPlacement++;
                }

                $semiFinalRound = $final->round - 1;

                if ($semiFinalRound >= 1) {
                    $semiFinalLosers = $singleElimMatches
                        ->where('round', $semiFinalRound)
                        ->map(fn ($match) => $this->loserForMatch($match))
                        ->filter()
                        ->unique()
                        ->values();

                    $thirdPlaceMatch = $this->findThirdPlaceMatch($singleElimMatches, $final, $semiFinalLosers);

                    if ($thirdPlaceMatch !== null) {
                        $thirdPlaceWinnerId = $thirdPlaceMatch->winner_id;
                        $fourthPlaceId = $this->loserForMatch($thirdPlaceMatch);

                        if ($thirdPlaceWinnerId !== null && !isset($placements[$thirdPlaceWinnerId])) {
                            $placements[$thirdPlaceWinnerId] = 3;
                        }

                        if ($fourthPlaceId !== null && !isset($placements[$fourthPlaceId])) {
                            $placements[$fourthPlaceId] = 4;
                        }

                        $nextPlacement = 5;
                    } else {
                        $sortedSemiFinalLosers = $semiFinalLosers
                            ->sortBy(fn ($playerId) => $this->placementSortKey($players->get($playerId)))
                            ->values();

                        foreach ($sortedSemiFinalLosers as $playerId) {
                            if (!isset($placements[$playerId])) {
                                $placements[$playerId] = 3;
                            }
                        }

                        $nextPlacement = 5;
                    }
                }
            }
        }

        $remainingPlayerIds = $players
            ->sortBy(fn ($player) => $this->placementSortKey($player))
            ->pluck('player_id');

        foreach ($remainingPlayerIds as $playerId) {
            if (!isset($placements[$playerId])) {
                $placements[$playerId] = $nextPlacement++;
            }
        }

        return $placements;
    }

    private function findThirdPlaceMatch(Collection $singleElimMatches, MatchResult $final, Collection $semiFinalLosers): ?MatchResult
    {
        if ($semiFinalLosers->count() !== 2) {
            return null;
        }

        $semiFinalLoserIds = $semiFinalLosers->sort()->values()->all();

        return $singleElimMatches
            ->where('id', '!=', $final->id)
            ->first(function (MatchResult $match) use ($semiFinalLoserIds) {
                $participantIds = collect([$match->player1_id, $match->player2_id])
                    ->filter()
                    ->sort()
                    ->values()
                    ->all();

                return $participantIds === $semiFinalLoserIds;
            });
    }

    private function loserForMatch(MatchResult $match): ?int
    {
        if ($match->winner_id === null) {
            return null;
        }

        if ($match->player1_id === $match->winner_id) {
            return $match->player2_id;
        }

        if ($match->player2_id === $match->winner_id) {
            return $match->player1_id;
        }

        return null;
    }

    private function placementSortKey(?TournamentPlayer $player): array
    {
        if ($player === null) {
            return [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX];
        }

        return [
            $player->swiss_rank ?? PHP_INT_MAX,
            $player->seed ?? PHP_INT_MAX,
            $player->player_id,
        ];
    }

    private function pointsForPlacement(int $placement, bool $hasSingleElimPhase, bool $hasSwissPhase, bool $isSwissKing): int
    {
        $placementPoints = self::POINTS_BY_PLACEMENT[$placement] ?? 0;
        $swissKingPoints = $hasSwissPhase && $hasSingleElimPhase && $isSwissKing ? self::SWISS_KING_POINTS : 0;

        return $placementPoints + $swissKingPoints;
    }
}
