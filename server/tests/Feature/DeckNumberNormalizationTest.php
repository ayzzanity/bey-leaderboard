<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Services\ChallongeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckNumberNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_maps_unnumbered_name_to_deck_one_when_numbered_variant_exists(): void
    {
        $this->mock(ChallongeService::class, function ($mock) {
            $mock->shouldReceive('getTournament')->once()->andReturn([
                'tournament' => [
                    'id' => 3001,
                    'name' => 'Deck Test',
                    'started_at' => '2026-03-20T10:00:00Z',
                ],
            ]);

            $mock->shouldReceive('getParticipants')->once()->andReturn([
                ['participant' => ['id' => 11, 'name' => 'Ayz', 'seed' => 1, 'group_player_ids' => [101]]],
                ['participant' => ['id' => 12, 'name' => 'Ayz2', 'seed' => 2, 'group_player_ids' => [102]]],
            ]);

            $mock->shouldReceive('getMatches')->once()->andReturn([
                ['match' => ['player1_id' => 101, 'player2_id' => 102, 'winner_id' => 101, 'round' => 1, 'group_id' => 1]],
                ['match' => ['player1_id' => 11, 'player2_id' => 12, 'winner_id' => 11, 'round' => 1, 'group_id' => null]],
            ]);
        });

        $this->postJson('/api/admin/import-tournament', [
            'url' => 'https://challonge.com/deck_test',
        ])->assertOk();

        $this->assertDatabaseHas('players', ['name' => 'Ayz 1']);
        $this->assertDatabaseHas('players', ['name' => 'Ayz 2']);
        $this->assertDatabaseMissing('players', ['name' => 'Ayz']);
    }

    public function test_recalculation_normalizes_existing_unnumbered_player_to_deck_one(): void
    {
        $ayz = Player::query()->create(['name' => 'Ayz']);
        $ayzTwo = Player::query()->create(['name' => 'Ayz 2']);

        $tournament = Tournament::query()->create([
            'name' => 'Legacy Deck Test',
            'challonge_id' => 'legacy1',
            'challonge_url' => 'https://challonge.com/legacy1',
            'challonge_slug' => 'legacy1',
            'date' => '2026-03-20',
            'participants_count' => 2,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $ayz->id,
            'seed' => 1,
            'swiss_wins' => 1,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 0,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $ayzTwo->id,
            'seed' => 2,
            'swiss_wins' => 0,
            'swiss_losses' => 1,
            'swiss_rank' => 2,
            'buchholz_score' => 1,
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $ayz->id,
            'player2_id' => $ayzTwo->id,
            'winner_id' => $ayz->id,
            'round' => 1,
            'stage' => 'swiss',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $ayz->id,
            'player2_id' => $ayzTwo->id,
            'winner_id' => $ayz->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        $this->postJson('/api/admin/recalculate-leaderboard')->assertOk();

        $this->assertDatabaseHas('players', ['name' => 'Ayz 1']);
        $this->assertDatabaseHas('players', ['name' => 'Ayz 2']);
        $this->assertDatabaseMissing('players', ['name' => 'Ayz']);
    }
}
