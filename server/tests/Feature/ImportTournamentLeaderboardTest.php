<?php

namespace Tests\Feature;

use App\Models\PlayerStat;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use App\Services\ChallongeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTournamentLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_rebuilds_tournament_results_and_global_leaderboard(): void
    {
        $this->mock(ChallongeService::class, function ($mock) {
            $mock->shouldReceive('getTournament')->once()->andReturn([
                'tournament' => [
                    'id' => 9991,
                    'name' => 'March Masters',
                    'started_at' => '2026-03-18T10:00:00Z',
                ],
            ]);

            $mock->shouldReceive('getParticipants')->once()->andReturn([
                ['participant' => ['id' => 11, 'name' => 'Alice', 'seed' => 1, 'group_player_ids' => [101]]],
                ['participant' => ['id' => 12, 'name' => 'Bob', 'seed' => 2, 'group_player_ids' => [102]]],
                ['participant' => ['id' => 13, 'name' => 'Cara', 'seed' => 3, 'group_player_ids' => [103]]],
                ['participant' => ['id' => 14, 'name' => 'Dan', 'seed' => 4, 'group_player_ids' => [104]]],
            ]);

            $mock->shouldReceive('getMatches')->once()->andReturn([
                ['match' => ['player1_id' => 101, 'player2_id' => 102, 'winner_id' => 101, 'round' => 1, 'group_id' => 1]],
                ['match' => ['player1_id' => 103, 'player2_id' => 104, 'winner_id' => 103, 'round' => 1, 'group_id' => 1]],
                ['match' => ['player1_id' => 101, 'player2_id' => 103, 'winner_id' => 103, 'round' => 2, 'group_id' => 1]],
                ['match' => ['player1_id' => 102, 'player2_id' => 104, 'winner_id' => 102, 'round' => 2, 'group_id' => 1]],
                ['match' => ['player1_id' => 101, 'player2_id' => 104, 'winner_id' => 101, 'round' => 3, 'group_id' => 1]],
                ['match' => ['player1_id' => 102, 'player2_id' => 103, 'winner_id' => 103, 'round' => 3, 'group_id' => 1]],
                ['match' => ['player1_id' => 11, 'player2_id' => 12, 'winner_id' => 11, 'round' => 1, 'group_id' => null]],
                ['match' => ['player1_id' => 13, 'player2_id' => 14, 'winner_id' => 13, 'round' => 1, 'group_id' => null]],
                ['match' => ['player1_id' => 11, 'player2_id' => 13, 'winner_id' => 11, 'round' => 2, 'group_id' => null]],
            ]);
        });

        $response = $this->postJson('/api/admin/import-tournament', [
            'url' => 'https://challonge.com/march_masters',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Tournament imported successfully',
                'tournament_name' => 'March Masters',
            ]);

        $standings = TournamentPlayer::query()
            ->with('player')
            ->orderBy('swiss_rank')
            ->get()
            ->map(fn (TournamentPlayer $player) => [
                'name' => $player->player->name,
                'wins' => $player->swiss_wins,
                'losses' => $player->swiss_losses,
                'rank' => $player->swiss_rank,
            ])
            ->all();

        $this->assertSame([
            ['name' => 'Cara', 'wins' => 3, 'losses' => 0, 'rank' => 1],
            ['name' => 'Alice', 'wins' => 2, 'losses' => 1, 'rank' => 2],
            ['name' => 'Bob', 'wins' => 1, 'losses' => 2, 'rank' => 3],
            ['name' => 'Dan', 'wins' => 0, 'losses' => 3, 'rank' => 4],
        ], $standings);

        $placements = TournamentResult::query()
            ->with('player')
            ->orderBy('placement')
            ->get()
            ->map(fn (TournamentResult $result) => [
                'name' => $result->player->name,
                'placement' => $result->placement,
                'points' => $result->points_awarded,
            ])
            ->all();

        $this->assertSame([
            ['name' => 'Alice', 'placement' => 1, 'points' => 10],
            ['name' => 'Cara', 'placement' => 2, 'points' => 17],
            ['name' => 'Bob', 'placement' => 3, 'points' => 0],
            ['name' => 'Dan', 'placement' => 3, 'points' => 0],
        ], $placements);

        $stats = PlayerStat::query()
            ->with('player')
            ->orderByDesc('total_points')
            ->get()
            ->mapWithKeys(fn (PlayerStat $stat) => [
                $stat->player->name => [
                    'points' => $stat->total_points,
                    'tournaments' => $stat->tournaments_played,
                    'swiss_kings' => $stat->swiss_kings,
                ],
            ])
            ->all();

        $this->assertSame([
            'Cara' => ['points' => 17, 'tournaments' => 1, 'swiss_kings' => 1],
            'Alice' => ['points' => 10, 'tournaments' => 1, 'swiss_kings' => 0],
            'Bob' => ['points' => 0, 'tournaments' => 1, 'swiss_kings' => 0],
            'Dan' => ['points' => 0, 'tournaments' => 1, 'swiss_kings' => 0],
        ], $stats);

        $this->getJson('/api/leaderboard')
            ->assertOk()
            ->assertExactJson([
                'open' => [
                    [
                    'rank' => 1,
                    'player_id' => 3,
                    'name' => 'Cara',
                    'points' => 17,
                    'tournaments' => 1,
                    'championships' => 0,
                    'second_place' => 1,
                    'third_place' => 0,
                    'fourth_place' => 0,
                    'swiss_kings' => 1,
                    ],
                    [
                    'rank' => 2,
                    'player_id' => 1,
                    'name' => 'Alice',
                    'points' => 10,
                    'tournaments' => 1,
                    'championships' => 1,
                    'second_place' => 0,
                    'third_place' => 0,
                    'fourth_place' => 0,
                    'swiss_kings' => 0,
                    ],
                    [
                    'rank' => 3,
                    'player_id' => 2,
                    'name' => 'Bob',
                    'points' => 0,
                    'tournaments' => 1,
                    'championships' => 0,
                    'second_place' => 0,
                    'third_place' => 1,
                    'fourth_place' => 0,
                    'swiss_kings' => 0,
                    ],
                    [
                    'rank' => 4,
                    'player_id' => 4,
                    'name' => 'Dan',
                    'points' => 0,
                    'tournaments' => 1,
                    'championships' => 0,
                    'second_place' => 0,
                    'third_place' => 1,
                    'fourth_place' => 0,
                    'swiss_kings' => 0,
                    ],
                ],
                'junior' => [],
            ]);
    }
}
