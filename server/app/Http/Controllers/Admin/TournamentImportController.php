<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Services\ChallongeService;
use App\Services\SwissService;

use App\Models\Tournament;
use App\Models\Player;
use App\Models\TournamentPlayer;
use App\Models\MatchResult;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TournamentImportController extends Controller
{
    public function import(Request $request, ChallongeService $challonge, SwissService $swissService)
    {
        // 1️⃣ Validate input
        $validator = Validator::make($request->all(), [
            'url' => ['required', 'string', 'regex:/^(https?:\/\/)?(www\.)?challonge\.com\/[A-Za-z0-9_-]+$/']
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $url = $request->input('url');

        // 2️⃣ Extract slug
        $slug = basename(parse_url($url, PHP_URL_PATH));

        // 3️⃣ Check if already imported by slug or url
        $existingTournament = Tournament::where('challonge_url', $url)
            ->first();

        if ($existingTournament) {
            return response()->json([
                'message' => 'This tournament has already been imported.',
                'tournament_id' => $existingTournament->id
            ], 409);
        }

        // Log::info("Importing tournament with slug: {$slug}");
        // 4️⃣ Get tournament data
        $tournamentData = $challonge->getTournament($slug);
        $participantsData = $challonge->getParticipants($slug);
        $matchesData = $challonge->getMatches($slug);

        // 5️⃣ Begin transaction
        DB::beginTransaction();

        try {

            // 1️⃣ Save tournament
            $tournament = Tournament::create([
                'name' => $tournamentData['tournament']['name'],
                'challonge_id' => $tournamentData['tournament']['id'],
                'challonge_url' => $url,
                'date' => date('Y-m-d', strtotime($tournamentData['tournament']['started_at'])),
                // 'date' => $tournamentData['tournament']['started_at'],
                'participants_count' => count($participantsData)
            ]);

            $playerMap = [];       // participant_id -> player_id
            $groupPlayerMap = [];  // group_player_id -> participant_id

            // 2️⃣ Save players + tournament players
            foreach ($participantsData as $p) {

                $participant = $p['participant'];

                $player = Player::firstOrCreate([
                    'name' => $participant['name']
                ]);

                // Map participant → player
                $playerMap[$participant['id']] = $player->id;

                // Map group_player_ids → participant
                if (!empty($participant['group_player_ids'])) {
                    foreach ($participant['group_player_ids'] as $gid) {
                        $groupPlayerMap[$gid] = $participant['id'];
                    }
                }

                TournamentPlayer::create([
                    'tournament_id' => $tournament->id,
                    'player_id' => $player->id,
                    'seed' => $participant['seed']
                ]);
            }

            // 3️⃣ Save matches
            foreach ($matchesData as $m) {

                $match = $m['match'];

                $player1ParticipantId = $match['player1_id'];
                $player2ParticipantId = $match['player2_id'];
                $winnerParticipantId  = $match['winner_id'];

                // If Swiss match, convert group_player_ids → participant_ids
                if ($match['group_id'] !== null) {
                    $player1ParticipantId = $groupPlayerMap[$player1ParticipantId] ?? null;
                    $player2ParticipantId = $groupPlayerMap[$player2ParticipantId] ?? null;
                    $winnerParticipantId  = $groupPlayerMap[$winnerParticipantId] ?? null;
                }

                MatchResult::create([
                    'tournament_id' => $tournament->id,
                    'player1_id' => $playerMap[$player1ParticipantId] ?? null,
                    'player2_id' => $playerMap[$player2ParticipantId] ?? null,
                    'winner_id' => $playerMap[$winnerParticipantId] ?? null,
                    'round' => $match['round'],
                    'stage' => $match['group_id'] !== null ? 'swiss' : 'topcut'
                ]);
            }

            DB::commit();

            $swissService->calculate($tournament->id);

            return response()->json([
                'message' => 'Tournament imported successfully',
                'tournament_id' => $tournament->id
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error("Error importing tournament: " . $e->getMessage());

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }

        // return response()->json([
        //     'tournament' => $tournament,
        //     'participants' => $participants,
        //     'matches' => $matches
        // ]);
    }
}
