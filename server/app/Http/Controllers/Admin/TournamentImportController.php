<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Services\ChallongeService;
use App\Services\LeaderboardAggregationService;
use App\Services\PlayerNameResolverService;
use App\Services\SwissService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TournamentImportController extends Controller
{
    public function finalize(LeaderboardAggregationService $leaderboardAggregation)
    {
        @set_time_limit(300);

        $leaderboardAggregation->rebuildPlayerStats();

        return response()->json([
            'message' => 'Tournament imports finalized successfully.',
        ]);
    }

    public function import(
        Request $request,
        ChallongeService $challonge,
        SwissService $swissService,
        LeaderboardAggregationService $leaderboardAggregation,
        PlayerNameResolverService $playerNameResolver,
    ) {
        @set_time_limit(0);

        $urls = $this->extractUrls($request);

        if (count($urls) === 0) {
            throw ValidationException::withMessages([
                'url' => 'At least one Challonge URL is required.',
            ]);
        }

        $results = [];
        $successfulTournamentIds = [];

        foreach ($urls as $url) {
            $result = $this->importSingleTournament(
                $url,
                $challonge,
                $swissService,
                $leaderboardAggregation,
                $playerNameResolver,
            );
            $results[] = $result;

            if (($result['status'] ?? 500) === 200 && isset($result['tournament_id'])) {
                $successfulTournamentIds[] = (int) $result['tournament_id'];
            }
        }

        if (!empty($successfulTournamentIds)) {
            if (!$request->boolean('defer_player_stats_rebuild')) {
                $leaderboardAggregation->rebuildPlayerStats();
            }
        }

        if (count($results) === 1) {
            return response()->json($results[0], $results[0]['status']);
        }

        $successCount = collect($results)->where('status', 200)->count();

        return response()->json([
            'message' => sprintf('Imported %d of %d tournament(s).', $successCount, count($results)),
            'results' => $results,
        ]);
    }

    private function extractUrls(Request $request): array
    {
        $urls = [];

        if ($request->filled('urls') && is_array($request->input('urls'))) {
            $urls = $request->input('urls');
        } elseif ($request->filled('url')) {
            $urls = preg_split('/[\r\n,]+/', $request->input('url')) ?: [];
        }

        $urls = array_map(fn ($url) => trim((string) $url), $urls);
        $urls = array_values(array_filter($urls, fn ($url) => $url !== ''));
        $urls = array_values(array_unique($urls));

        foreach ($urls as $url) {
            $validator = Validator::make(['url' => $url], [
                'url' => ['required', 'string', 'regex:/^(https?:\/\/)?(www\.)?challonge\.com\/[A-Za-z0-9_-]+$/'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }

        return $urls;
    }

    private function importSingleTournament(
        string $url,
        ChallongeService $challonge,
        SwissService $swissService,
        LeaderboardAggregationService $leaderboardAggregation,
        PlayerNameResolverService $playerNameResolver,
    ): array {
        $slug = basename(parse_url($url, PHP_URL_PATH));

        $existingTournament = Tournament::where('challonge_url', $url)
            ->orWhere('challonge_slug', $slug)
            ->first();

        if ($existingTournament) {
            return [
                'status' => 409,
                'message' => 'This tournament has already been imported.',
                'tournament_id' => $existingTournament->id,
                'tournament_name' => $existingTournament->name,
                'url' => $url,
            ];
        }

        $tournamentData = $challonge->getTournament($slug);
        $participantsData = $challonge->getParticipants($slug);
        $matchesData = $challonge->getMatches($slug);
        $tournamentType = (string) ($tournamentData['tournament']['tournament_type'] ?? '');
        $hasGroupedSwissMatches = collect($matchesData)
            ->contains(fn ($matchRow) => ($matchRow['match']['group_id'] ?? null) !== null);

        DB::beginTransaction();

        try {
            $deckOneBases = $playerNameResolver->collectDeckOneBases(
                array_map(fn ($participant) => $participant['participant']['name'], $participantsData)
            );

            $tournament = Tournament::create([
                'name' => $tournamentData['tournament']['name'],
                'challonge_id' => $tournamentData['tournament']['id'],
                'challonge_url' => $url,
                'challonge_slug' => $slug,
                'date' => date('Y-m-d', strtotime($tournamentData['tournament']['started_at'])),
                'participants_count' => count($participantsData),
            ]);

            $playerMap = [];
            $groupPlayerMap = [];

            foreach ($participantsData as $participantRow) {
                $participant = $participantRow['participant'];
                $player = $playerNameResolver->resolvePlayer($participant['name'], $deckOneBases);

                $playerMap[$participant['id']] = $player->id;

                foreach (($participant['group_player_ids'] ?? []) as $groupPlayerId) {
                    $groupPlayerMap[$groupPlayerId] = $participant['id'];
                }

                TournamentPlayer::create([
                    'tournament_id' => $tournament->id,
                    'player_id' => $player->id,
                    'imported_name' => $participant['name'],
                    'seed' => $participant['seed'],
                ]);
            }

            foreach ($matchesData as $matchRow) {
                $match = $matchRow['match'];
                [$player1Score, $player2Score] = $this->parseScores($match['scores_csv'] ?? null);
                $stage = $this->determineMatchStage($match, $tournamentType, $hasGroupedSwissMatches);

                $player1ParticipantId = $match['player1_id'];
                $player2ParticipantId = $match['player2_id'];
                $winnerParticipantId = $match['winner_id'];

                if ($stage === 'swiss' && $match['group_id'] !== null) {
                    $player1ParticipantId = $groupPlayerMap[$player1ParticipantId] ?? null;
                    $player2ParticipantId = $groupPlayerMap[$player2ParticipantId] ?? null;
                    $winnerParticipantId = $groupPlayerMap[$winnerParticipantId] ?? null;
                }

                MatchResult::create([
                    'tournament_id' => $tournament->id,
                    'player1_id' => $playerMap[$player1ParticipantId] ?? null,
                    'player2_id' => $playerMap[$player2ParticipantId] ?? null,
                    'winner_id' => $playerMap[$winnerParticipantId] ?? null,
                    'player1_score' => $player1Score,
                    'player2_score' => $player2Score,
                    'round' => $match['round'],
                    'stage' => $stage,
                ]);
            }

            $swissService->calculate($tournament->id);
            $leaderboardAggregation->rebuildTournamentResults($tournament->id);

            DB::commit();

            return [
                'status' => 200,
                'message' => 'Tournament imported successfully',
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'url' => $url,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error importing tournament: " . $e->getMessage());

            return [
                'status' => 500,
                'message' => 'Server error while importing tournament',
                'error' => $e->getMessage(),
                'url' => $url,
            ];
        }
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
}
