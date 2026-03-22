<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlayerCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LeaderboardAdminController extends Controller
{
    public function recalculate(PlayerCorrectionService $playerCorrection): \Illuminate\Http\JsonResponse
    {
        @set_time_limit(300);

        $playerCorrection->recalculateAllTournaments();

        return response()->json([
            'message' => 'Leaderboard recalculated successfully.',
        ]);
    }

    public function prepareRecalculation(PlayerCorrectionService $playerCorrection): \Illuminate\Http\JsonResponse
    {
        @set_time_limit(300);

        $tournamentIds = $playerCorrection->prepareRecalculation();

        return response()->json([
            'tournament_ids' => $tournamentIds,
            'total' => count($tournamentIds),
        ]);
    }

    public function recalculateTournament(int $id, PlayerCorrectionService $playerCorrection): \Illuminate\Http\JsonResponse
    {
        @set_time_limit(300);

        $playerCorrection->recalculateTournament($id);

        return response()->json([
            'message' => 'Tournament recalculated successfully.',
            'tournament_id' => $id,
        ]);
    }

    public function finalizeRecalculation(PlayerCorrectionService $playerCorrection): \Illuminate\Http\JsonResponse
    {
        @set_time_limit(300);

        $playerCorrection->finalizeRecalculation();

        return response()->json([
            'message' => 'Leaderboard recalculated successfully.',
        ]);
    }

    public function correctPlayerName(Request $request, PlayerCorrectionService $playerCorrection): \Illuminate\Http\JsonResponse
    {
        @set_time_limit(0);

        $validator = Validator::make($request->all(), [
            'alias_name' => ['required', 'string', 'max:255'],
            'canonical_name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $player = $playerCorrection->applyManualCorrection(
            $request->string('alias_name')->toString(),
            $request->string('canonical_name')->toString(),
        );

        return response()->json([
            'message' => 'Player name correction applied successfully.',
            'player_id' => $player->id,
            'player_name' => $player->name,
        ]);
    }
}
