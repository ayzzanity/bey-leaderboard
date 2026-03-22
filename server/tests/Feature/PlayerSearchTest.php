<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_search_can_exclude_the_current_corrected_alias(): void
    {
        $alice = Player::query()->create(['name' => 'Alice']);
        $bob = Player::query()->create(['name' => 'Bob']);

        PlayerAlias::query()->create([
            'player_id' => $alice->id,
            'alias_name' => 'Probinsyano 1',
            'alias_key' => 'probinsyano 1',
        ]);

        PlayerAlias::query()->create([
            'player_id' => $bob->id,
            'alias_name' => 'Bobby',
            'alias_key' => 'bobby',
        ]);

        $this->getJson('/api/players?q=Probinsyano&exclude_alias_name=Probinsyano 1')
            ->assertOk()
            ->assertJsonCount(0);

        $this->getJson('/api/players?q=Bob&exclude_alias_name=Probinsyano 1')
            ->assertOk()
            ->assertJsonPath('0.name', 'Bob');
    }
}
