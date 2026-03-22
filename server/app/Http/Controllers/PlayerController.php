<?php

namespace App\Http\Controllers;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\PlayerAlias;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function show(int $id)
    {
        $player = Player::query()
            ->with(['stats', 'aliases', 'tournamentPlayers.tournament', 'tournamentResults.tournament'])
            ->findOrFail($id);

        $leaderboard = PlayerStat::query()
            ->orderByDesc('total_points')
            ->orderByDesc('championships')
            ->orderByDesc('second_place')
            ->orderByDesc('third_place')
            ->orderByDesc('fourth_place')
            ->orderBy('player_id')
            ->pluck('player_id')
            ->values();

        $globalRank = $leaderboard->search($player->id);
        $stats = $player->stats;

        $history = $player->tournamentPlayers
            ->sortByDesc(fn ($entry) => $entry->tournament?->date)
            ->values()
            ->map(function (TournamentPlayer $entry) use ($player) {
                $tournament = $entry->tournament;
                $hasSwissPhase = $this->hasSwissPhase((int) $entry->tournament_id);
                $topCutSize = $this->determineTopCutSize((int) $entry->tournament_id);
                $qualifiedForTopCut = $entry->swiss_rank !== null && $topCutSize > 0 && $entry->swiss_rank <= $topCutSize;

                $tournamentResult = TournamentResult::query()
                    ->where('tournament_id', $entry->tournament_id)
                    ->where('player_id', $player->id)
                    ->first();

                return [
                    'tournament_id' => $tournament?->id,
                    'tournament_name' => $tournament?->name,
                    'date' => $tournament?->date,
                    'rank_label' => $tournamentResult !== null && (!$hasSwissPhase || $qualifiedForTopCut)
                        ? $this->placementLabel($tournamentResult->placement)
                        : sprintf('Swiss #%d', $entry->swiss_rank ?? 0),
                    'swiss_rank' => $entry->swiss_rank,
                    'top_cut_placement' => $tournamentResult?->placement,
                    'points_awarded' => $tournamentResult?->points_awarded ?? 0,
                ];
            });

        return [
            'id' => $player->id,
            'name' => $player->name,
            'summary' => [
                'rank' => $globalRank === false ? null : $globalRank + 1,
                'total_points' => $stats?->total_points ?? 0,
                'tournaments_joined' => $stats?->tournaments_played ?? 0,
                'championships' => $stats?->championships ?? 0,
                'swiss_kings' => $stats?->swiss_kings ?? 0,
                'finishers' => $stats?->second_place ?? 0,
                'birdie_kings' => $this->birdieKingCount($player->id),
            ],
            'known_aliases' => $player->aliases
                ->pluck('alias_name')
                ->filter(fn ($alias) => $alias !== $player->name)
                ->values(),
            'tournaments_joined' => $history->values(),
        ];
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $excludeAliasName = trim((string) $request->query('exclude_alias_name', ''));

        if ($query === '') {
            return [];
        }

        $playerIds = Player::query()
            ->where('name', 'like', "%{$query}%")
            ->pluck('id');

        $aliasPlayerIds = PlayerAlias::query()
            ->where('alias_name', 'like', "%{$query}%")
            ->pluck('player_id');

        $ids = $playerIds
            ->merge($aliasPlayerIds)
            ->unique()
            ->values();

        return Player::query()
            ->with('aliases')
            ->whereIn('id', $ids)
            ->when($excludeAliasName !== '', function ($queryBuilder) use ($excludeAliasName) {
                $queryBuilder
                    ->where('name', '!=', $excludeAliasName)
                    ->whereDoesntHave('aliases', fn ($aliasQuery) => $aliasQuery->where('alias_name', $excludeAliasName));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'aliases' => $player->aliases
                    ->pluck('alias_name')
                    ->filter(fn ($alias) => $alias !== $player->name)
                    ->values(),
            ]);
    }

    private function birdieKingCount(int $playerId): int
    {
        $count = 0;

        $tournamentIds = TournamentPlayer::query()
            ->where('player_id', $playerId)
            ->pluck('tournament_id');

        foreach ($tournamentIds as $tournamentId) {
            if (!$this->hasSwissPhase((int) $tournamentId)) {
                continue;
            }

            $birdie = TournamentPlayer::query()
                ->with('player')
                ->where('tournament_id', $tournamentId)
                ->where('swiss_wins', 0)
                ->orderBy('swiss_rank')
                ->orderBy('seed')
                ->first();

            if ($birdie?->player_id === $playerId) {
                $count++;
            }
        }

        return $count;
    }

    private function hasSwissPhase(int $tournamentId): bool
    {
        return MatchResult::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'swiss')
            ->whereNotNull('winner_id')
            ->exists();
    }

    private function determineTopCutSize(int $tournamentId): int
    {
        $singleElimRounds = MatchResult::query()
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'single_elim')
            ->get()
            ->groupBy('round')
            ->sortKeys();

        if ($singleElimRounds->isEmpty()) {
            return 0;
        }

        $roundSummaries = $singleElimRounds
            ->map(function ($roundMatches, $round) {
                $participants = $roundMatches
                    ->flatMap(fn ($match) => [$match->player1_id, $match->player2_id])
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'round' => (int) $round,
                    'count' => $participants->count(),
                ];
            })
            ->values();

        $mainRound = $roundSummaries->first();

        foreach ($roundSummaries as $index => $summary) {
            $nextSummary = $roundSummaries->get($index + 1);

            if ($nextSummary === null || $nextSummary['count'] < $summary['count']) {
                $mainRound = $summary;
                break;
            }
        }

        return $mainRound['count'];
    }

    private function placementLabel(int $placement): string
    {
        return match (true) {
            $placement === 1 => 'Champ',
            $placement === 2 => 'Finisher',
            default => sprintf('Top %d', $this->nextPlacementBucket($placement)),
        };
    }

    private function nextPlacementBucket(int $placement): int
    {
        $bucket = 4;

        while ($bucket < $placement) {
            $bucket *= 2;
        }

        return $bucket;
    }
}
