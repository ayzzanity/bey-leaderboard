<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use App\Services\ChallongeStandingsService;
use App\Services\PlayerNameResolverService;
use App\Services\PlayerCorrectionService;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    private const AGE_CATEGORIES = ['junior', 'open'];
    private const EVENT_TYPES = ['casual', 'tournament'];

    public function index()
    {
        $query = Tournament::query()
            ->when(request('q'), function ($builder, $search) {
                $builder->where('name', 'like', '%' . trim($search) . '%');
            })
            ->when(request('player'), function ($builder, $playerSearch) {
                $playerSearch = trim($playerSearch);

                $builder->whereHas('players.player', function ($playerQuery) use ($playerSearch) {
                    $playerQuery->where('name', 'like', "%{$playerSearch}%")
                        ->orWhereHas('aliases', fn ($aliasQuery) => $aliasQuery->where('alias_name', 'like', "%{$playerSearch}%"));
                });
            });

        return $query->orderBy('date', 'desc')->get();
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'age_category' => ['nullable', 'in:' . implode(',', self::AGE_CATEGORIES)],
            'event_type' => ['nullable', 'in:' . implode(',', self::EVENT_TYPES)],
        ]);

        $tournament = Tournament::query()->findOrFail($id);
        $tournament->update($validated);

        return $tournament->fresh();
    }

    public function destroy(int $id, PlayerCorrectionService $playerCorrection)
    {
        $tournament = Tournament::query()->findOrFail($id);

        DB::transaction(function () use ($tournament, $playerCorrection) {
            MatchResult::query()->where('tournament_id', $tournament->id)->delete();
            TournamentResult::query()->where('tournament_id', $tournament->id)->delete();
            TournamentPlayer::query()->where('tournament_id', $tournament->id)->delete();
            $tournament->delete();

            $playerCorrection->rebuildAfterTournamentDeletion();
        });

        return response()->json([
            'message' => 'Tournament deleted successfully.',
        ]);
    }

    public function batchUpdate(Request $request)
    {
        $validated = $request->validate([
            'tournament_ids' => ['required', 'array', 'min:1'],
            'tournament_ids.*' => ['integer', 'exists:tournaments,id'],
            'age_category' => ['nullable', 'in:' . implode(',', self::AGE_CATEGORIES)],
            'event_type' => ['nullable', 'in:' . implode(',', self::EVENT_TYPES)],
        ]);

        Tournament::query()
            ->whereIn('id', $validated['tournament_ids'])
            ->update([
                'age_category' => $validated['age_category'] ?? null,
                'event_type' => $validated['event_type'] ?? null,
            ]);

        return response()->json([
            'message' => 'Tournament categories updated successfully.',
            'updated_count' => count($validated['tournament_ids']),
        ]);
    }

    public function show($id, ChallongeStandingsService $challongeStandings, PlayerNameResolverService $nameResolver)
    {
        $tournament = Tournament::with([
            'players.player.aliases',
            'results.player',
        ])->findOrFail($id);

        $matches = MatchResult::query()
            ->where('tournament_id', $tournament->id)
            ->orderBy('round')
            ->orderBy('id')
            ->get();
        $hasSwissPhase = $matches->where('stage', 'swiss')->whereNotNull('winner_id')->isNotEmpty();
        $hasSingleElimPhase = $matches->where('stage', 'single_elim')->whereNotNull('winner_id')->isNotEmpty();

        $playerLookup = $this->buildPlayerLookup($tournament->players, $nameResolver);
        $challongeRows = $tournament->challonge_slug
            ? $challongeStandings->getGroupStandings($tournament->challonge_slug)
            : null;

        if ($challongeRows !== null) {
            $players = collect($challongeRows)
                ->map(fn ($row) => $this->mapChallongeStandingsRow($row, $playerLookup, $nameResolver));

            $topCutPlayerIds = $players
                ->where('qualified_for_top_cut', true)
                ->pluck('player_id')
                ->filter()
                ->values();
        } else {
            $topCutSize = $this->determineTopCutSize($matches);
            $topCutPlayerIds = $tournament->players
                ->whereNotNull('swiss_rank')
                ->sortBy('swiss_rank')
                ->take($topCutSize)
                ->pluck('player_id')
                ->values();

            $players = $tournament->players
                ->sortBy([
                    ['swiss_rank', 'asc'],
                    ['swiss_wins', 'desc'],
                    ['buchholz_score', 'desc'],
                    ['seed', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->map(fn ($player) => $this->buildFallbackGroupStageRow($player, $matches, $topCutPlayerIds));
        }

        $playerDisplayNames = $players
            ->filter(fn ($row) => !empty($row['player_id']))
            ->mapWithKeys(fn ($row) => [$row['player_id'] => $row['display_name']])
            ->all();

        $topCutResults = $tournament->results
            ->filter(fn ($result) => $topCutPlayerIds->contains($result->player_id))
            ->sortBy([
                ['placement', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($hasSingleElimPhase && $topCutResults->isEmpty()) {
            $topCutResults = $tournament->results
                ->sortBy([
                    ['placement', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
        }

        $topCut = $topCutResults
            ->map(fn ($result) => [
                'id' => $result->id,
                'placement' => $result->placement,
                'placement_label' => $this->placementLabel($result->placement),
                'points_awarded' => $result->points_awarded,
                'player' => $result->player,
                'display_name' => $playerDisplayNames[$result->player_id] ?? $result->player?->name,
            ]);

        return [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'age_category' => $tournament->age_category,
            'event_type' => $tournament->event_type,
            'has_swiss_phase' => $hasSwissPhase,
            'has_single_elim_phase' => $hasSingleElimPhase,
            'has_top_cut' => $topCut->isNotEmpty(),
            'date' => $tournament->date,
            'participants_count' => $tournament->participants_count,
            'challonge_url' => $tournament->challonge_url,
            'challonge_slug' => $tournament->challonge_slug,
            'summary' => [
                'champ' => $hasSingleElimPhase ? $this->summaryPlayer($topCutResults->firstWhere('placement', 1)) : null,
                'finisher' => $hasSingleElimPhase ? $this->summaryPlayer($topCutResults->firstWhere('placement', 2)) : null,
                'swiss_king' => $hasSwissPhase ? ($challongeRows !== null
                    ? $this->summaryFromStandingsRow($players->firstWhere('rank', 1))
                    : $this->summaryPlayer($tournament->players->sortBy('swiss_rank')->firstWhere('swiss_rank', 1))) : null,
                'birdie_king' => $hasSwissPhase ? ($challongeRows !== null
                    ? $this->summaryFromStandingsRow($players->firstWhere('wins', 0))
                    : $this->summaryPlayer(
                        $tournament->players
                            ->where('swiss_wins', 0)
                            ->sortBy([
                                ['swiss_rank', 'asc'],
                                ['seed', 'asc'],
                                ['id', 'asc'],
                            ])
                            ->first()
                    )) : null,
            ],
            'swiss_standings' => $players->values(),
            'top_cut_standings' => $topCut->values(),
        ];
    }

    private function summaryPlayer(TournamentPlayer|TournamentResult|null $entry): ?array
    {
        if ($entry === null || $entry->player === null) {
            return null;
        }

        return [
            'player_id' => $entry->player->id,
            'name' => $entry->player->name,
        ];
    }

    private function summaryFromStandingsRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'player_id' => $row['player_id'] ?? null,
            'name' => $row['display_name'] ?? null,
        ];
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

    private function buildFallbackGroupStageRow(TournamentPlayer $player, $matches, $topCutPlayerIds): array
    {
        $tournamentPlayers = TournamentPlayer::query()
            ->where('tournament_id', $player->tournament_id)
            ->get()
            ->keyBy('player_id');

        $playerMatches = $matches
            ->where('stage', 'swiss')
            ->filter(fn ($match) => $match->player1_id === $player->player_id || $match->player2_id === $player->player_id)
            ->values();

        $pointsScored = 0;
        $pointsAllowed = 0;
        $matchHistory = [];

        foreach ($playerMatches as $match) {
            $isPlayerOne = $match->player1_id === $player->player_id;
            $scored = $isPlayerOne ? ($match->player1_score ?? 0) : ($match->player2_score ?? 0);
            $allowed = $isPlayerOne ? ($match->player2_score ?? 0) : ($match->player1_score ?? 0);

            $pointsScored += $scored;
            $pointsAllowed += $allowed;
            $matchHistory[] = $match->winner_id === $player->player_id ? 'W' : 'L';
        }

        $tb = 0;

        foreach ($playerMatches as $match) {
            if ($match->winner_id !== $player->player_id) {
                continue;
            }

            $opponentId = $match->player1_id === $player->player_id ? $match->player2_id : $match->player1_id;
            $opponent = $opponentId !== null ? ($tournamentPlayers[$opponentId] ?? null) : null;

            if ($opponent === null) {
                continue;
            }

            if (
                (int) $opponent->swiss_wins === (int) $player->swiss_wins &&
                (int) $opponent->swiss_losses === (int) $player->swiss_losses
            ) {
                $tb++;
            }
        }

        return [
            'id' => $player->id,
            'rank' => $player->swiss_rank,
            'alias_name' => $player->imported_name ?? $player->player?->name,
            'player_id' => $player->player_id,
            'player' => $player->player,
            'display_name' => $this->formatParticipantDisplayName($player->imported_name ?? $player->player?->name ?? '', $player->player?->name),
            'record' => sprintf('%d - %d - 0', $player->swiss_wins, $player->swiss_losses),
            'score' => (float) $player->swiss_wins,
            'tb' => $tb,
            'wins' => $player->swiss_wins,
            'losses' => $player->swiss_losses,
            'ties' => 0,
            'swiss_rank' => $player->swiss_rank,
            'swiss_wins' => $player->swiss_wins,
            'swiss_losses' => $player->swiss_losses,
            'total_points_scored' => $pointsScored,
            'buchholz_score' => $player->buchholz_score,
            'points_diff' => $pointsScored - $pointsAllowed,
            'match_history' => $matchHistory,
            'qualified_for_top_cut' => $topCutPlayerIds->contains($player->player_id),
        ];
    }

    private function buildPlayerLookup($tournamentPlayers, PlayerNameResolverService $nameResolver): array
    {
        $lookup = [];

        foreach ($tournamentPlayers as $tournamentPlayer) {
            $player = $tournamentPlayer->player;

            if ($player === null) {
                continue;
            }

            $lookup[$nameResolver->aliasKey($player->name)] = $player;

            foreach ($player->aliases as $alias) {
                $lookup[$nameResolver->aliasKey($alias->alias_name)] = $player;
            }
        }

        return $lookup;
    }

    private function mapChallongeStandingsRow(array $row, array $playerLookup, PlayerNameResolverService $nameResolver): array
    {
        $player = $playerLookup[$nameResolver->aliasKey($row['participant_name'])] ?? null;
        $displayName = $this->formatParticipantDisplayName($row['participant_name'], $player?->name);

        return [
            'id' => $player?->id ?? $row['rank'],
            'rank' => $row['rank'],
            'alias_name' => $row['participant_name'],
            'player_id' => $player?->id,
            'player' => $player,
            'display_name' => $displayName,
            'record' => sprintf('%d - %d - %d', $row['wins'], $row['losses'], $row['ties']),
            'score' => $row['score'],
            'tb' => $row['tb'],
            'wins' => $row['wins'],
            'losses' => $row['losses'],
            'ties' => $row['ties'],
            'swiss_rank' => $row['rank'],
            'swiss_wins' => $row['wins'],
            'swiss_losses' => $row['losses'],
            'total_points_scored' => $row['pts'],
            'buchholz_score' => $row['buchholz'],
            'points_diff' => $row['points_diff'],
            'match_history' => $row['match_history'],
            'qualified_for_top_cut' => $row['qualified_for_top_cut'],
        ];
    }

    private function formatParticipantDisplayName(string $importedName, ?string $canonicalName): string
    {
        if ($canonicalName === null || $canonicalName === $importedName) {
            return $importedName;
        }

        return sprintf('%s (%s)', $importedName, $canonicalName);
    }

    private function determineTopCutSize($matches): int
    {
        $singleElimRounds = $matches
            ->where('stage', 'single_elim')
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
}
