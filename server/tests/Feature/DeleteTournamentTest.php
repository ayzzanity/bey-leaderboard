<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_can_be_deleted_and_leaderboard_stats_are_rebuilt(): void
    {
        $player = Player::query()->create(['name' => 'Ayz']);

        $tournament = Tournament::query()->create([
            'name' => 'To Delete',
            'challonge_id' => 'delete-me',
            'challonge_url' => 'https://challonge.com/delete-me',
            'challonge_slug' => 'delete-me',
            'date' => '2026-03-20',
            'participants_count' => 1,
            'age_category' => 'open',
            'event_type' => 'tournament',
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'seed' => 1,
            'swiss_wins' => 1,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 0,
        ]);

        TournamentResult::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player->id,
            'player2_id' => null,
            'winner_id' => $player->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        PlayerStat::query()->create([
            'player_id' => $player->id,
            'total_points' => 10,
            'championships' => 1,
            'second_place' => 0,
            'third_place' => 0,
            'fourth_place' => 0,
            'swiss_kings' => 1,
            'tournaments_played' => 1,
        ]);

        $this->deleteJson("/api/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Tournament deleted successfully.');

        $this->assertDatabaseMissing('tournaments', ['id' => $tournament->id]);
        $this->assertDatabaseMissing('tournament_players', ['tournament_id' => $tournament->id]);
        $this->assertDatabaseMissing('tournament_results', ['tournament_id' => $tournament->id]);
        $this->assertDatabaseMissing('match_results', ['tournament_id' => $tournament->id]);
        $this->assertDatabaseMissing('player_stats', ['player_id' => $player->id]);
    }
}
