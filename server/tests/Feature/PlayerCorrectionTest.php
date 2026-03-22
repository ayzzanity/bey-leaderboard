<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\PlayerAlias;
use App\Models\PlayerStat;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_player_correction_merges_history_and_rebuilds_stats(): void
    {
        $ayz = Player::query()->create(['name' => 'Ayz']);
        $ayzz = Player::query()->create(['name' => 'Ayzz']);
        $bob = Player::query()->create(['name' => 'Bob']);
        $cara = Player::query()->create(['name' => 'Cara']);

        $tournamentOne = Tournament::query()->create([
            'name' => 'Qualifier One',
            'challonge_id' => 'q1',
            'challonge_url' => 'https://challonge.com/q1',
            'challonge_slug' => 'q1',
            'date' => '2026-03-01',
            'participants_count' => 2,
        ]);

        $tournamentTwo = Tournament::query()->create([
            'name' => 'Qualifier Two',
            'challonge_id' => 'q2',
            'challonge_url' => 'https://challonge.com/q2',
            'challonge_slug' => 'q2',
            'date' => '2026-03-02',
            'participants_count' => 2,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournamentOne->id,
            'player_id' => $ayzz->id,
            'seed' => 1,
            'swiss_wins' => 1,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 0,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournamentOne->id,
            'player_id' => $bob->id,
            'seed' => 2,
            'swiss_wins' => 0,
            'swiss_losses' => 1,
            'swiss_rank' => 2,
            'buchholz_score' => 1,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player_id' => $ayz->id,
            'seed' => 1,
            'swiss_wins' => 1,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 0,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player_id' => $cara->id,
            'seed' => 2,
            'swiss_wins' => 0,
            'swiss_losses' => 1,
            'swiss_rank' => 2,
            'buchholz_score' => 1,
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournamentOne->id,
            'player1_id' => $ayzz->id,
            'player2_id' => $bob->id,
            'winner_id' => $ayzz->id,
            'round' => 1,
            'stage' => 'swiss',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournamentOne->id,
            'player1_id' => $ayzz->id,
            'player2_id' => $bob->id,
            'winner_id' => $ayzz->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player1_id' => $ayz->id,
            'player2_id' => $cara->id,
            'winner_id' => $ayz->id,
            'round' => 1,
            'stage' => 'swiss',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player1_id' => $ayz->id,
            'player2_id' => $cara->id,
            'winner_id' => $ayz->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        $response = $this->postJson('/api/admin/player-corrections', [
            'alias_name' => 'Ayzz',
            'canonical_name' => 'Ayz',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Player name correction applied successfully.',
                'player_name' => 'Ayz',
            ]);

        $this->assertDatabaseMissing('players', ['name' => 'Ayzz']);
        $this->assertDatabaseHas('player_aliases', ['alias_key' => 'ayzz', 'player_id' => $ayz->id]);
        $this->assertSame(0, MatchResult::query()->where('winner_id', $ayzz->id)->count());

        $stat = PlayerStat::query()->where('player_id', $ayz->id)->firstOrFail();

        $this->assertSame(40, $stat->total_points);
        $this->assertSame(2, $stat->championships);
        $this->assertSame(2, $stat->tournaments_played);
        $this->assertSame(2, $stat->swiss_kings);

        $this->getJson('/api/leaderboard')
            ->assertOk()
            ->assertJsonPath('open.0.name', 'Ayz')
            ->assertJsonPath('open.0.points', 40)
            ->assertJsonPath('open.0.tournaments', 2);
    }
}
