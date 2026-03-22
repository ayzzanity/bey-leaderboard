<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_details_include_summary_and_top_cut_standings(): void
    {
        $ayz = Player::query()->create(['name' => 'Ayz']);
        $bob = Player::query()->create(['name' => 'Bob']);
        $cara = Player::query()->create(['name' => 'Cara']);
        $dan = Player::query()->create(['name' => 'Dan']);

        $tournament = Tournament::query()->create([
            'name' => 'March Masters',
            'challonge_id' => 'm1',
            'challonge_url' => 'https://challonge.com/m1',
            'challonge_slug' => 'm1',
            'date' => '2026-03-18',
            'participants_count' => 4,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $ayz->id,
            'seed' => 1,
            'swiss_wins' => 3,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 4,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $bob->id,
            'seed' => 2,
            'swiss_wins' => 2,
            'swiss_losses' => 1,
            'swiss_rank' => 2,
            'buchholz_score' => 3,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $cara->id,
            'seed' => 3,
            'swiss_wins' => 1,
            'swiss_losses' => 2,
            'swiss_rank' => 3,
            'buchholz_score' => 2,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $dan->id,
            'seed' => 4,
            'swiss_wins' => 0,
            'swiss_losses' => 3,
            'swiss_rank' => 4,
            'buchholz_score' => 1,
        ]);

        TournamentResult::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $bob->id,
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        TournamentResult::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $ayz->id,
            'placement' => 2,
            'points_awarded' => 7,
        ]);

        TournamentResult::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $cara->id,
            'placement' => 3,
            'points_awarded' => 5,
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $ayz->id,
            'player2_id' => $bob->id,
            'winner_id' => $ayz->id,
            'player1_score' => 5,
            'player2_score' => 2,
            'round' => 1,
            'stage' => 'swiss',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $cara->id,
            'player2_id' => $dan->id,
            'winner_id' => $cara->id,
            'player1_score' => 4,
            'player2_score' => 1,
            'round' => 1,
            'stage' => 'swiss',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $bob->id,
            'player2_id' => $ayz->id,
            'winner_id' => $bob->id,
            'player1_score' => 3,
            'player2_score' => 2,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        $this->getJson("/api/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJsonPath('summary.champ.name', 'Bob')
            ->assertJsonPath('summary.finisher.name', 'Ayz')
            ->assertJsonPath('summary.swiss_king.name', 'Ayz')
            ->assertJsonPath('summary.birdie_king.name', 'Dan')
            ->assertJsonPath('top_cut_standings.0.placement_label', 'Champ')
            ->assertJsonPath('top_cut_standings.0.player.name', 'Bob')
            ->assertJsonPath('top_cut_standings.1.player.name', 'Ayz')
            ->assertJsonPath('swiss_standings.0.player.name', 'Ayz')
            ->assertJsonPath('swiss_standings.0.total_points_scored', 5)
            ->assertJsonPath('swiss_standings.0.points_diff', 3)
            ->assertJsonPath('swiss_standings.0.match_history.0', 'W')
            ->assertJsonPath('swiss_standings.0.qualified_for_top_cut', true);
    }
}
