<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\PlayerAlias;
use App\Models\PlayerStat;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_profile_returns_summary_and_tournament_history(): void
    {
        $alice = Player::query()->create(['name' => 'Alice']);
        $bob = Player::query()->create(['name' => 'Bob']);
        $cara = Player::query()->create(['name' => 'Cara']);
        PlayerAlias::query()->create([
            'player_id' => $alice->id,
            'alias_name' => 'Probinsyano 1',
            'alias_key' => 'probinsyano 1',
        ]);

        PlayerStat::query()->create([
            'player_id' => $alice->id,
            'total_points' => 17,
            'championships' => 1,
            'second_place' => 1,
            'third_place' => 0,
            'fourth_place' => 0,
            'swiss_kings' => 1,
            'tournaments_played' => 2,
        ]);

        PlayerStat::query()->create([
            'player_id' => $bob->id,
            'total_points' => 10,
            'championships' => 0,
            'second_place' => 0,
            'third_place' => 1,
            'fourth_place' => 0,
            'swiss_kings' => 0,
            'tournaments_played' => 1,
        ]);

        $tournamentOne = Tournament::query()->create([
            'name' => 'Tournament One',
            'challonge_id' => 't1',
            'challonge_url' => 'https://challonge.com/t1',
            'challonge_slug' => 't1',
            'date' => '2026-03-10',
            'participants_count' => 2,
        ]);

        $tournamentTwo = Tournament::query()->create([
            'name' => 'Tournament Two',
            'challonge_id' => 't2',
            'challonge_url' => 'https://challonge.com/t2',
            'challonge_slug' => 't2',
            'date' => '2026-03-15',
            'participants_count' => 3,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournamentOne->id,
            'player_id' => $alice->id,
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
            'player_id' => $alice->id,
            'seed' => 1,
            'swiss_wins' => 2,
            'swiss_losses' => 1,
            'swiss_rank' => 2,
            'buchholz_score' => 5,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player_id' => $bob->id,
            'seed' => 2,
            'swiss_wins' => 3,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 4,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player_id' => $cara->id,
            'seed' => 3,
            'swiss_wins' => 0,
            'swiss_losses' => 3,
            'swiss_rank' => 3,
            'buchholz_score' => 2,
        ]);

        TournamentResult::query()->create([
            'tournament_id' => $tournamentOne->id,
            'player_id' => $alice->id,
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        TournamentResult::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player_id' => $alice->id,
            'placement' => 2,
            'points_awarded' => 7,
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournamentOne->id,
            'player1_id' => $alice->id,
            'player2_id' => $bob->id,
            'winner_id' => $alice->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player1_id' => $alice->id,
            'player2_id' => $bob->id,
            'winner_id' => $bob->id,
            'round' => 2,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournamentTwo->id,
            'player1_id' => $bob->id,
            'player2_id' => $cara->id,
            'winner_id' => $bob->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        $this->getJson("/api/players/{$alice->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Alice')
            ->assertJsonPath('summary.rank', 1)
            ->assertJsonPath('summary.total_points', 17)
            ->assertJsonPath('summary.tournaments_joined', 2)
            ->assertJsonPath('summary.championships', 1)
            ->assertJsonPath('summary.swiss_kings', 1)
            ->assertJsonPath('summary.finishers', 1)
            ->assertJsonPath('summary.birdie_kings', 0)
            ->assertJsonPath('known_aliases.0', 'Probinsyano 1')
            ->assertJsonPath('tournaments_joined.0.tournament_name', 'Tournament Two')
            ->assertJsonPath('tournaments_joined.0.rank_label', 'Finisher')
            ->assertJsonPath('tournaments_joined.1.rank_label', 'Champ');

        $this->getJson('/api/players?q=Probinsyano')
            ->assertOk()
            ->assertJsonPath('0.name', 'Alice')
            ->assertJsonPath('0.aliases.0', 'Probinsyano 1');
    }
}
