<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\PlayerAlias;
use App\Models\PlayerStat;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;

class PlayerCorrectionService
{
    public function __construct(
        private readonly PlayerNameResolverService $nameResolver,
        private readonly SwissService $swissService,
        private readonly LeaderboardAggregationService $leaderboardAggregation,
        private readonly ChallongeService $challongeService,
    ) {
    }

    public function applyManualCorrection(string $aliasName, string $canonicalName): Player
    {
        return DB::transaction(function () use ($aliasName, $canonicalName) {
            $canonicalDisplayName = $this->nameResolver->normalizeCanonicalName($canonicalName);
            $canonicalPlayer = Player::query()->firstOrCreate([
                'name' => $canonicalDisplayName,
            ]);

            $aliasCandidates = collect([
                trim($aliasName),
                $this->nameResolver->sanitizeImportedName($aliasName),
                $this->nameResolver->normalizeCanonicalName($aliasName),
            ])->filter()->unique()->values();

            $affectedTournamentIds = TournamentPlayer::query()
                ->where('player_id', $canonicalPlayer->id)
                ->orWhereIn('player_id', Player::query()
                    ->whereIn('name', $aliasCandidates)
                    ->pluck('id'))
                ->distinct()
                ->orderBy('tournament_id')
                ->pluck('tournament_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($aliasCandidates as $candidate) {
                $this->nameResolver->storeAlias($canonicalPlayer, $candidate);
            }

            $playersToMerge = Player::query()
                ->whereIn('name', $aliasCandidates)
                ->where('id', '!=', $canonicalPlayer->id)
                ->get();

            foreach ($playersToMerge as $sourcePlayer) {
                $this->mergePlayerIntoCanonical($sourcePlayer, $canonicalPlayer);
            }

            $this->rebuildAffectedTournaments($affectedTournamentIds, false);

            return $canonicalPlayer->fresh();
        });
    }

    public function recalculateAllTournaments(): void
    {
        $this->normalizePlayersByImportRules();

        $tournamentIds = TournamentPlayer::query()
            ->distinct()
            ->orderBy('tournament_id')
            ->pluck('tournament_id');

        TournamentResult::query()->delete();

        foreach ($tournamentIds as $tournamentId) {
            $this->rebuildAffectedTournaments([(int) $tournamentId], true, false);
        }

        $this->leaderboardAggregation->rebuildPlayerStats();
    }

    public function prepareRecalculation(): array
    {
        $this->normalizePlayersByImportRules();

        TournamentResult::query()->delete();

        return TournamentPlayer::query()
            ->distinct()
            ->orderBy('tournament_id')
            ->pluck('tournament_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function recalculateTournament(int $tournamentId, bool $refreshScores = true): void
    {
        $this->rebuildAffectedTournaments([$tournamentId], $refreshScores, false);
    }

    public function finalizeRecalculation(): void
    {
        $this->leaderboardAggregation->rebuildPlayerStats();
    }

    public function rebuildAfterTournamentDeletion(): void
    {
        $this->leaderboardAggregation->rebuildPlayerStats();
    }

    private function normalizePlayersByImportRules(): void
    {
        $deckOneBases = $this->nameResolver->collectDeckOneBases([
            ...Player::query()->pluck('name')->all(),
            ...PlayerAlias::query()->pluck('alias_name')->all(),
        ]);

        $players = Player::query()->orderBy('id')->get();

        foreach ($players as $player) {
            $normalizedName = $this->nameResolver->normalizeCanonicalName($player->name, $deckOneBases);

            if ($normalizedName === $player->name) {
                $this->nameResolver->storeAlias($player, $player->name);
                continue;
            }

            $canonicalPlayer = Player::query()->firstOrCreate([
                'name' => $normalizedName,
            ]);

            $this->nameResolver->storeAlias($canonicalPlayer, $player->name);
            $this->nameResolver->storeAlias($canonicalPlayer, $normalizedName);

            if ($canonicalPlayer->id !== $player->id) {
                $this->mergePlayerIntoCanonical($player, $canonicalPlayer);
            }
        }
    }

    private function refreshTournamentMatchScores(int $tournamentId): void
    {
        $tournament = Tournament::query()->find($tournamentId);

        if ($tournament === null || empty($tournament->challonge_slug)) {
            return;
        }

        try {
            $tournamentData = $this->challongeService->getTournament($tournament->challonge_slug);
            $participantsData = $this->challongeService->getParticipants($tournament->challonge_slug);
            $matchesData = $this->challongeService->getMatches($tournament->challonge_slug);
        } catch (ConnectionException) {
            return;
        }

        if (!is_array($participantsData) || !is_array($matchesData)) {
            return;
        }

        $deckOneBases = $this->nameResolver->collectDeckOneBases(
            array_map(fn ($participant) => $participant['participant']['name'] ?? '', $participantsData)
        );
        $tournamentType = (string) ($tournamentData['tournament']['tournament_type'] ?? '');
        $hasGroupedSwissMatches = collect($matchesData)
            ->contains(fn ($matchRow) => ($matchRow['match']['group_id'] ?? null) !== null);

        $playerMap = [];
        $groupPlayerMap = [];

        foreach ($participantsData as $participantRow) {
            $participant = $participantRow['participant'] ?? [];
            $participantId = $participant['id'] ?? null;
            $participantName = $participant['name'] ?? null;

            if ($participantId === null || $participantName === null) {
                continue;
            }

            $player = $this->nameResolver->resolvePlayer($participantName, $deckOneBases);
            $playerMap[$participantId] = $player->id;

            TournamentPlayer::query()
                ->where('tournament_id', $tournamentId)
                ->where('player_id', $player->id)
                ->where(function ($query) use ($participantName) {
                    $query->whereNull('imported_name')
                        ->orWhere('imported_name', '')
                        ->orWhere('imported_name', $participantName);
                })
                ->update(['imported_name' => $participantName]);

            foreach (($participant['group_player_ids'] ?? []) as $groupPlayerId) {
                $groupPlayerMap[$groupPlayerId] = $participantId;
            }
        }

        foreach ($matchesData as $matchRow) {
            $match = $matchRow['match'] ?? [];

            $stage = $this->determineMatchStage($match, $tournamentType, $hasGroupedSwissMatches);
            $player1ParticipantId = $match['player1_id'] ?? null;
            $player2ParticipantId = $match['player2_id'] ?? null;
            $winnerParticipantId = $match['winner_id'] ?? null;

            if ($stage === 'swiss' && ($match['group_id'] ?? null) !== null) {
                $player1ParticipantId = $groupPlayerMap[$player1ParticipantId] ?? null;
                $player2ParticipantId = $groupPlayerMap[$player2ParticipantId] ?? null;
                $winnerParticipantId = $groupPlayerMap[$winnerParticipantId] ?? null;
            }

            $player1Id = $playerMap[$player1ParticipantId] ?? null;
            $player2Id = $playerMap[$player2ParticipantId] ?? null;
            $winnerId = $playerMap[$winnerParticipantId] ?? null;

            [$player1Score, $player2Score] = $this->parseScores($match['scores_csv'] ?? null);

            $existingMatch = MatchResult::query()
                ->where('tournament_id', $tournamentId)
                ->where('round', $match['round'] ?? 0)
                ->where(function ($query) use ($player1Id, $player2Id) {
                    $query
                        ->where(function ($inner) use ($player1Id, $player2Id) {
                            $inner->where('player1_id', $player1Id)->where('player2_id', $player2Id);
                        })
                        ->orWhere(function ($inner) use ($player1Id, $player2Id) {
                            $inner->where('player1_id', $player2Id)->where('player2_id', $player1Id);
                        });
                })
                ->first();

            if ($existingMatch === null) {
                continue;
            }

            $existingMatch->stage = $stage;
            $existingMatch->winner_id = $winnerId;
            $existingMatch->player1_score = $existingMatch->player1_id === $player1Id ? $player1Score : $player2Score;
            $existingMatch->player2_score = $existingMatch->player2_id === $player2Id ? $player2Score : $player1Score;
            $existingMatch->save();
        }
    }

    private function parseScores(?string $scoresCsv): array
    {
        if ($scoresCsv === null || trim($scoresCsv) === '') {
            return [null, null];
        }

        $player1Score = 0;
        $player2Score = 0;

        foreach (explode(',', $scoresCsv) as $setScore) {
            if (preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $setScore, $matches) !== 1) {
                continue;
            }

            $player1Score += (int) $matches[1];
            $player2Score += (int) $matches[2];
        }

        return [$player1Score, $player2Score];
    }

    private function determineMatchStage(array $match, string $tournamentType, bool $hasGroupedSwissMatches): string
    {
        if (($match['group_id'] ?? null) !== null) {
            return 'swiss';
        }

        if (!$hasGroupedSwissMatches && str_contains(strtolower($tournamentType), 'swiss')) {
            return 'swiss';
        }

        return 'single_elim';
    }

    private function mergePlayerIntoCanonical(Player $sourcePlayer, Player $canonicalPlayer): void
    {
        $this->moveMatchResults($sourcePlayer->id, $canonicalPlayer->id);

        TournamentPlayer::query()
            ->where('player_id', $sourcePlayer->id)
            ->where(function ($query) use ($sourcePlayer) {
                $query->whereNull('imported_name')
                    ->orWhere('imported_name', '')
                    ->orWhere('imported_name', $sourcePlayer->name);
            })
            ->update(['imported_name' => $sourcePlayer->name]);

        TournamentPlayer::query()
            ->where('player_id', $sourcePlayer->id)
            ->update(['player_id' => $canonicalPlayer->id]);

        TournamentResult::query()
            ->where('player_id', $sourcePlayer->id)
            ->update(['player_id' => $canonicalPlayer->id]);

        PlayerAlias::query()
            ->where('player_id', $sourcePlayer->id)
            ->update(['player_id' => $canonicalPlayer->id]);

        PlayerStat::query()->where('player_id', $sourcePlayer->id)->delete();
        $sourcePlayer->delete();
    }

    private function moveMatchResults(int $fromPlayerId, int $toPlayerId): void
    {
        MatchResult::query()->where('player1_id', $fromPlayerId)->update(['player1_id' => $toPlayerId]);
        MatchResult::query()->where('player2_id', $fromPlayerId)->update(['player2_id' => $toPlayerId]);
        MatchResult::query()->where('winner_id', $fromPlayerId)->update(['winner_id' => $toPlayerId]);
    }

    private function deduplicateTournamentPlayers(int $tournamentId): void
    {
        $duplicates = TournamentPlayer::query()
            ->where('tournament_id', $tournamentId)
            ->orderBy('id')
            ->get()
            ->groupBy('player_id')
            ->filter(fn ($rows) => $rows->count() > 1);

        foreach ($duplicates as $rows) {
            $keeper = $rows
                ->sortBy([
                    ['swiss_rank', 'asc'],
                    ['swiss_wins', 'desc'],
                    ['buchholz_score', 'desc'],
                    ['id', 'asc'],
                ])
                ->first();

            foreach ($rows->where('id', '!=', $keeper->id) as $duplicate) {
                $keeper->imported_name = $keeper->imported_name ?: $duplicate->imported_name;
                $keeper->seed = $keeper->seed ?? $duplicate->seed;
                $keeper->swiss_wins = max($keeper->swiss_wins, $duplicate->swiss_wins);
                $keeper->swiss_losses = max($keeper->swiss_losses, $duplicate->swiss_losses);
                $keeper->buchholz_score = max($keeper->buchholz_score ?? 0, $duplicate->buchholz_score ?? 0);
                $keeper->swiss_rank = $keeper->swiss_rank ?? $duplicate->swiss_rank;
                $keeper->save();

                $duplicate->delete();
            }
        }
    }

    private function rebuildAffectedTournaments(array $tournamentIds, bool $refreshScores, bool $rebuildPlayerStats = true): void
    {
        foreach (collect($tournamentIds)->filter()->unique()->values() as $tournamentId) {
            $tournamentId = (int) $tournamentId;

            if ($refreshScores) {
                $this->refreshTournamentMatchScores($tournamentId);
            }

            $this->deduplicateTournamentPlayers($tournamentId);
            $this->swissService->calculate($tournamentId);
            $this->leaderboardAggregation->rebuildTournamentResults($tournamentId);
        }

        if ($rebuildPlayerStats) {
            $this->leaderboardAggregation->rebuildPlayerStats();
        }
    }
}
