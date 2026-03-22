<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentResult;
use App\Models\TournamentPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_is_split_by_age_category(): void
    {
        $alice = Player::query()->create(['name' => 'Alice']);
        $bob = Player::query()->create(['name' => 'Bob']);

        $openTournament = Tournament::query()->create([
            'name' => 'Open Event',
            'challonge_id' => 'open-event',
            'challonge_url' => 'https://challonge.com/open-event',
            'challonge_slug' => 'open-event',
            'date' => '2026-03-18',
            'participants_count' => 2,
            'age_category' => 'open',
            'event_type' => 'tournament',
        ]);

        $juniorTournament = Tournament::query()->create([
            'name' => 'Junior Event',
            'challonge_id' => 'junior-event',
            'challonge_url' => 'https://challonge.com/junior-event',
            'challonge_slug' => 'junior-event',
            'date' => '2026-03-19',
            'participants_count' => 2,
            'age_category' => 'junior',
            'event_type' => 'tournament',
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $openTournament->id,
            'player_id' => $alice->id,
            'seed' => 1,
            'swiss_wins' => 2,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 0,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $juniorTournament->id,
            'player_id' => $bob->id,
            'seed' => 1,
            'swiss_wins' => 2,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 0,
        ]);

        TournamentResult::query()->create([
            'tournament_id' => $openTournament->id,
            'player_id' => $alice->id,
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        TournamentResult::query()->create([
            'tournament_id' => $juniorTournament->id,
            'player_id' => $bob->id,
            'placement' => 1,
            'points_awarded' => 10,
        ]);

        $this->getJson('/api/leaderboard')
            ->assertOk()
            ->assertJsonPath('open.0.name', 'Alice')
            ->assertJsonPath('open.0.rank', 1)
            ->assertJsonPath('junior.0.name', 'Bob')
            ->assertJsonPath('junior.0.rank', 1);
    }
}
