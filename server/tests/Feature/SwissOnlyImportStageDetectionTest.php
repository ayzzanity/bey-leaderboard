<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use App\Services\ChallongeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwissOnlyImportStageDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_swiss_tournament_without_group_ids_is_imported_as_swiss_phase(): void
    {
        $this->mock(ChallongeService::class, function ($mock) {
            $mock->shouldReceive('getTournament')->once()->andReturn([
                'tournament' => [
                    'id' => 5551,
                    'name' => 'Open Category - Swiss Round',
                    'started_at' => '2026-02-28T03:44:00Z',
                    'tournament_type' => 'swiss',
                ],
            ]);

            $mock->shouldReceive('getParticipants')->once()->andReturn([
                ['participant' => ['id' => 11, 'name' => 'Archer Rojas', 'seed' => 1, 'group_player_ids' => []]],
                ['participant' => ['id' => 12, 'name' => 'Clark Zaider Chiong', 'seed' => 2, 'group_player_ids' => []]],
            ]);

            $mock->shouldReceive('getMatches')->once()->andReturn([
                ['match' => ['player1_id' => 11, 'player2_id' => 12, 'winner_id' => 11, 'round' => 1, 'group_id' => null, 'scores_csv' => '5-0']],
            ]);
        });

        $this->postJson('/api/admin/import-tournament', [
            'url' => 'https://challonge.com/open_swiss_round',
        ])->assertOk();

        $this->assertDatabaseHas('match_results', [
            'stage' => 'swiss',
            'round' => 1,
        ]);

        $this->assertDatabaseMissing('match_results', [
            'stage' => 'single_elim',
            'round' => 1,
        ]);

        $this->assertDatabaseHas('tournament_results', [
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        $this->getJson('/api/tournaments/1')
            ->assertOk()
            ->assertJsonPath('has_swiss_phase', true)
            ->assertJsonPath('has_single_elim_phase', false)
            ->assertJsonPath('has_top_cut', false)
            ->assertJsonCount(0, 'top_cut_standings')
            ->assertJsonPath('summary.champ', null)
            ->assertJsonPath('summary.finisher', null)
            ->assertJsonPath('summary.swiss_king.name', 'Archer Rojas')
            ->assertJsonPath('swiss_standings.0.total_points_scored', 5)
            ->assertJsonPath('swiss_standings.0.points_diff', 5);
    }
}
