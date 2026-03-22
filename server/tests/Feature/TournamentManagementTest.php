<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerAlias;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournaments_can_be_searched_by_name_and_player(): void
    {
        $ayz = Player::query()->create(['name' => 'Ayz 1']);
        $other = Player::query()->create(['name' => 'Other']);

        PlayerAlias::query()->create([
            'player_id' => $ayz->id,
            'alias_name' => 'Probinsyano 1',
            'alias_key' => 'probinsyano 1',
        ]);

        $march = Tournament::query()->create([
            'name' => 'March Masters',
            'challonge_id' => 'march',
            'challonge_url' => 'https://challonge.com/march',
            'challonge_slug' => 'march',
            'date' => '2026-03-18',
            'participants_count' => 1,
        ]);

        $april = Tournament::query()->create([
            'name' => 'April Clash',
            'challonge_id' => 'april',
            'challonge_url' => 'https://challonge.com/april',
            'challonge_slug' => 'april',
            'date' => '2026-03-19',
            'participants_count' => 1,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $march->id,
            'player_id' => $ayz->id,
            'seed' => 1,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $april->id,
            'player_id' => $other->id,
            'seed' => 1,
        ]);

        $this->getJson('/api/tournaments?q=March')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'March Masters');

        $this->getJson('/api/tournaments?player=Probinsyano')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'March Masters');
    }

    public function test_tournaments_can_be_batch_updated(): void
    {
        $one = Tournament::query()->create([
            'name' => 'Tournament One',
            'challonge_id' => 'one',
            'challonge_url' => 'https://challonge.com/one',
            'challonge_slug' => 'one',
            'date' => '2026-03-18',
            'participants_count' => 1,
        ]);

        $two = Tournament::query()->create([
            'name' => 'Tournament Two',
            'challonge_id' => 'two',
            'challonge_url' => 'https://challonge.com/two',
            'challonge_slug' => 'two',
            'date' => '2026-03-19',
            'participants_count' => 1,
        ]);

        $this->patchJson('/api/tournaments/batch', [
            'tournament_ids' => [$one->id, $two->id],
            'age_category' => 'junior',
            'event_type' => 'casual',
        ])
            ->assertOk()
            ->assertJsonPath('updated_count', 2);

        $this->assertDatabaseHas('tournaments', [
            'id' => $one->id,
            'age_category' => 'junior',
            'event_type' => 'casual',
        ]);

        $this->assertDatabaseHas('tournaments', [
            'id' => $two->id,
            'age_category' => 'junior',
            'event_type' => 'casual',
        ]);
    }
}
