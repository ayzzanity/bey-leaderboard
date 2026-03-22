<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use App\Services\LeaderboardAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentFormatScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_swiss_only_tournament_awards_points_only_to_swiss_king(): void
    {
        $alice = Player::query()->create(['name' => 'Alice']);
        $bob = Player::query()->create(['name' => 'Bob']);
        $cara = Player::query()->create(['name' => 'Cara']);

        $tournament = Tournament::query()->create([
            'name' => 'Swiss Only Cup',
            'challonge_id' => 'swiss-only',
            'challonge_url' => 'https://challonge.com/swiss-only',
            'challonge_slug' => 'swiss-only',
            'date' => '2026-03-20',
            'participants_count' => 3,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $alice->id,
            'seed' => 1,
            'swiss_wins' => 3,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 6,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $bob->id,
            'seed' => 2,
            'swiss_wins' => 2,
            'swiss_losses' => 1,
            'swiss_rank' => 2,
            'buchholz_score' => 4,
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

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $alice->id,
            'player2_id' => $bob->id,
            'winner_id' => $alice->id,
            'round' => 1,
            'stage' => 'swiss',
        ]);

        app(LeaderboardAggregationService::class)->rebuildTournamentResults($tournament->id);
        app(LeaderboardAggregationService::class)->rebuildPlayerStats();

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $alice->id,
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        $this->assertDatabaseMissing('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $bob->id,
        ]);

        $this->assertDatabaseMissing('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $cara->id,
        ]);

        $aliceStats = PlayerStat::query()->where('player_id', $alice->id)->firstOrFail();
        $bobStats = PlayerStat::query()->where('player_id', $bob->id)->firstOrFail();

        $this->assertSame(10, $aliceStats->total_points);
        $this->assertSame(0, $aliceStats->championships);
        $this->assertSame(0, $bobStats->total_points);
    }

    public function test_single_elimination_only_tournament_uses_bracket_placements(): void
    {
        $alice = Player::query()->create(['name' => 'Alice']);
        $bob = Player::query()->create(['name' => 'Bob']);

        $tournament = Tournament::query()->create([
            'name' => 'Top Cut Only',
            'challonge_id' => 'top-cut-only',
            'challonge_url' => 'https://challonge.com/top-cut-only',
            'challonge_slug' => 'top-cut-only',
            'date' => '2026-03-20',
            'participants_count' => 2,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $alice->id,
            'seed' => 1,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $bob->id,
            'seed' => 2,
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $alice->id,
            'player2_id' => $bob->id,
            'winner_id' => $bob->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        app(LeaderboardAggregationService::class)->rebuildTournamentResults($tournament->id);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $bob->id,
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $alice->id,
            'placement' => 2,
            'points_awarded' => 7,
        ]);

        app(LeaderboardAggregationService::class)->rebuildPlayerStats();

        $bobStats = PlayerStat::query()->where('player_id', $bob->id)->firstOrFail();
        $aliceStats = PlayerStat::query()->where('player_id', $alice->id)->firstOrFail();

        $this->assertSame(1, $bobStats->championships);
        $this->assertSame(0, $bobStats->swiss_kings);
        $this->assertSame(0, $aliceStats->swiss_kings);

        $this->getJson("/api/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJsonPath('summary.champ.name', 'Bob')
            ->assertJsonPath('summary.finisher.name', 'Alice')
            ->assertJsonPath('summary.swiss_king', null)
            ->assertJsonPath('summary.birdie_king', null);
    }

    public function test_swiss_king_gets_points_in_mixed_format_tournament(): void
    {
        $alice = Player::query()->create(['name' => 'Alice']);
        $bob = Player::query()->create(['name' => 'Bob']);
        $cara = Player::query()->create(['name' => 'Cara']);
        $dan = Player::query()->create(['name' => 'Dan']);

        Tournament::query()->create([
            'id' => 1,
            'name' => 'Mixed Format',
            'challonge_id' => 'mixed-format',
            'challonge_url' => 'https://challonge.com/mixed-format',
            'challonge_slug' => 'mixed-format',
            'date' => '2026-03-20',
            'participants_count' => 4,
        ]);

        foreach ([
            [$alice, 2, 2, 1, 2],
            [$bob, 1, 1, 2, 3],
            [$cara, 3, 3, 0, 1],
            [$dan, 4, 0, 3, 4],
        ] as [$player, $seed, $wins, $losses, $rank]) {
            TournamentPlayer::query()->create([
                'tournament_id' => 1,
                'player_id' => $player->id,
                'seed' => $seed,
                'swiss_wins' => $wins,
                'swiss_losses' => $losses,
                'swiss_rank' => $rank,
                'buchholz_score' => 0,
            ]);
        }

        MatchResult::query()->create([
            'tournament_id' => 1,
            'player1_id' => $alice->id,
            'player2_id' => $bob->id,
            'winner_id' => $alice->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => 1,
            'player1_id' => $cara->id,
            'player2_id' => $alice->id,
            'winner_id' => $cara->id,
            'round' => 1,
            'stage' => 'swiss',
        ]);

        app(LeaderboardAggregationService::class)->rebuildTournamentResults(1);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => 1,
            'player_id' => $alice->id,
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => 1,
            'player_id' => $bob->id,
            'placement' => 2,
            'points_awarded' => 7,
        ]);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => 1,
            'player_id' => $cara->id,
            'placement' => 3,
            'points_awarded' => 10,
        ]);
    }

    public function test_semifinal_losers_tie_for_third_when_no_third_place_match_exists(): void
    {
        $alice = Player::query()->create(['name' => 'Alice']);
        $bob = Player::query()->create(['name' => 'Bob']);
        $cara = Player::query()->create(['name' => 'Cara']);
        $dan = Player::query()->create(['name' => 'Dan']);

        $tournament = Tournament::query()->create([
            'name' => 'No Third Place Match',
            'challonge_id' => 'no-third-place',
            'challonge_url' => 'https://challonge.com/no-third-place',
            'challonge_slug' => 'no-third-place',
            'date' => '2026-03-20',
            'participants_count' => 4,
        ]);

        foreach ([[$alice, 1], [$bob, 2], [$cara, 3], [$dan, 4]] as [$player, $seed]) {
            TournamentPlayer::query()->create([
                'tournament_id' => $tournament->id,
                'player_id' => $player->id,
                'seed' => $seed,
            ]);
        }

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $alice->id,
            'player2_id' => $bob->id,
            'winner_id' => $alice->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $cara->id,
            'player2_id' => $dan->id,
            'winner_id' => $cara->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $alice->id,
            'player2_id' => $cara->id,
            'winner_id' => $alice->id,
            'round' => 2,
            'stage' => 'single_elim',
        ]);

        app(LeaderboardAggregationService::class)->rebuildTournamentResults($tournament->id);

        $placements = TournamentResult::query()
            ->orderBy('placement')
            ->orderBy('player_id')
            ->get()
            ->map(fn (TournamentResult $result) => [
                'player_id' => $result->player_id,
                'placement' => $result->placement,
                'points_awarded' => $result->points_awarded,
            ])
            ->all();

        $this->assertSame([
            ['player_id' => $alice->id, 'placement' => 1, 'points_awarded' => 10],
            ['player_id' => $cara->id, 'placement' => 2, 'points_awarded' => 7],
            ['player_id' => $bob->id, 'placement' => 3, 'points_awarded' => 0],
            ['player_id' => $dan->id, 'placement' => 3, 'points_awarded' => 0],
        ], $placements);
    }

    public function test_third_place_match_assigns_distinct_third_and_fourth(): void
    {
        $alice = Player::query()->create(['name' => 'Alice']);
        $bob = Player::query()->create(['name' => 'Bob']);
        $cara = Player::query()->create(['name' => 'Cara']);
        $dan = Player::query()->create(['name' => 'Dan']);

        $tournament = Tournament::query()->create([
            'name' => 'With Third Place Match',
            'challonge_id' => 'with-third-place',
            'challonge_url' => 'https://challonge.com/with-third-place',
            'challonge_slug' => 'with-third-place',
            'date' => '2026-03-20',
            'participants_count' => 4,
        ]);

        foreach ([[$alice, 1], [$bob, 2], [$cara, 3], [$dan, 4]] as [$player, $seed]) {
            TournamentPlayer::query()->create([
                'tournament_id' => $tournament->id,
                'player_id' => $player->id,
                'seed' => $seed,
            ]);
        }

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $alice->id,
            'player2_id' => $bob->id,
            'winner_id' => $alice->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $cara->id,
            'player2_id' => $dan->id,
            'winner_id' => $cara->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $bob->id,
            'player2_id' => $dan->id,
            'winner_id' => $bob->id,
            'round' => 2,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $alice->id,
            'player2_id' => $cara->id,
            'winner_id' => $alice->id,
            'round' => 2,
            'stage' => 'single_elim',
        ]);

        app(LeaderboardAggregationService::class)->rebuildTournamentResults($tournament->id);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $bob->id,
            'placement' => 3,
            'points_awarded' => 0,
        ]);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $dan->id,
            'placement' => 4,
            'points_awarded' => 0,
        ]);
    }
}
