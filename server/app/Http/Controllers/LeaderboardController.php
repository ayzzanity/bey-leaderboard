<?php

namespace App\Http\Controllers;

use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use App\Models\MatchResult;
use App\Models\Player;
use Illuminate\Support\Collection;

class LeaderboardController extends Controller
{
    public function index()
    {
        return [
            'open' => $this->buildCategoryLeaderboard('open'),
            'junior' => $this->buildCategoryLeaderboard('junior'),
        ];
    }

    private function buildCategoryLeaderboard(string $ageCategory): Collection
    {
        $categoryTournamentIds = MatchResult::query()
            ->select('tournament_id')
            ->whereHas('tournament', function ($query) use ($ageCategory) {
                $query->where('age_category', $ageCategory);

                if ($ageCategory === 'open') {
                    $query->orWhereNull('age_category');
                }
            })
            ->distinct()
            ->pluck('tournament_id');

        $singleElimTournamentIds = MatchResult::query()
            ->where('stage', 'single_elim')
            ->whereNotNull('winner_id')
            ->whereIn('tournament_id', $categoryTournamentIds)
            ->distinct()
            ->pluck('tournament_id')
            ->map(fn ($id) => (int) $id);

        $results = TournamentResult::query()
            ->with('player')
            ->whereHas('tournament', function ($query) use ($ageCategory) {
                $query->where('age_category', $ageCategory);

                if ($ageCategory === 'open') {
                    $query->orWhereNull('age_category');
                }
            })
            ->get();

        $swissKings = TournamentPlayer::query()
            ->select('player_id')
            ->where('swiss_rank', 1)
            ->whereIn('tournament_id', $categoryTournamentIds)
            ->whereHas('tournament', function ($query) use ($ageCategory) {
                $query->where('age_category', $ageCategory);

                if ($ageCategory === 'open') {
                    $query->orWhereNull('age_category');
                }
            })
            ->get()
            ->groupBy('player_id')
            ->map->count();

        return $results
            ->groupBy('player_id')
            ->map(function (Collection $playerResults, $playerId) use ($swissKings, $singleElimTournamentIds) {
                /** @var TournamentResult $firstResult */
                $firstResult = $playerResults->first();
                /** @var Player|null $player */
                $player = $firstResult?->player;
                $singleElimResults = $playerResults->whereIn('tournament_id', $singleElimTournamentIds);

                return [
                    'player_id' => (int) $playerId,
                    'name' => $player?->name,
                    'points' => $playerResults->sum('points_awarded'),
                    'tournaments' => $playerResults->count(),
                    'championships' => $singleElimResults->where('placement', 1)->count(),
                    'second_place' => $singleElimResults->where('placement', 2)->count(),
                    'third_place' => $singleElimResults->where('placement', 3)->count(),
                    'fourth_place' => $singleElimResults->where('placement', 4)->count(),
                    'swiss_kings' => $swissKings->get((int) $playerId, 0),
                ];
            })
            ->sortBy([
                ['points', 'desc'],
                ['championships', 'desc'],
                ['second_place', 'desc'],
                ['third_place', 'desc'],
                ['fourth_place', 'desc'],
                ['player_id', 'asc'],
            ])
            ->values()
            ->map(function (array $player, int $index) {
                $player['rank'] = $index + 1;

                return $player;
            });
    }
}
