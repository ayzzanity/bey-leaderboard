<?php

namespace Tests\Feature;

use App\Services\ChallongeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkImportTournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_import_accepts_multiple_urls(): void
    {
        $this->mock(ChallongeService::class, function ($mock) {
            $mock->shouldReceive('getTournament')->twice()->andReturn(
                [
                    'tournament' => [
                        'id' => 5001,
                        'name' => 'Bulk One',
                        'started_at' => '2026-03-20T10:00:00Z',
                    ],
                ],
                [
                    'tournament' => [
                        'id' => 5002,
                        'name' => 'Bulk Two',
                        'started_at' => '2026-03-21T10:00:00Z',
                    ],
                ],
            );

            $mock->shouldReceive('getParticipants')->twice()->andReturn(
                [
                    ['participant' => ['id' => 11, 'name' => 'Alice', 'seed' => 1, 'group_player_ids' => [101]]],
                    ['participant' => ['id' => 12, 'name' => 'Bob', 'seed' => 2, 'group_player_ids' => [102]]],
                ],
                [
                    ['participant' => ['id' => 21, 'name' => 'Cara', 'seed' => 1, 'group_player_ids' => [201]]],
                    ['participant' => ['id' => 22, 'name' => 'Dan', 'seed' => 2, 'group_player_ids' => [202]]],
                ],
            );

            $mock->shouldReceive('getMatches')->twice()->andReturn(
                [
                    ['match' => ['player1_id' => 101, 'player2_id' => 102, 'winner_id' => 101, 'round' => 1, 'group_id' => 1]],
                    ['match' => ['player1_id' => 11, 'player2_id' => 12, 'winner_id' => 11, 'round' => 1, 'group_id' => null]],
                ],
                [
                    ['match' => ['player1_id' => 201, 'player2_id' => 202, 'winner_id' => 201, 'round' => 1, 'group_id' => 1]],
                    ['match' => ['player1_id' => 21, 'player2_id' => 22, 'winner_id' => 21, 'round' => 1, 'group_id' => null]],
                ],
            );
        });

        $this->postJson('/api/admin/import-tournament', [
            'urls' => [
                'https://challonge.com/bulk_one',
                'https://challonge.com/bulk_two',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Imported 2 of 2 tournament(s).')
            ->assertJsonPath('results.0.tournament_name', 'Bulk One')
            ->assertJsonPath('results.1.tournament_name', 'Bulk Two');
    }
}
