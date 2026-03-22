<?php

namespace Tests\Feature;

use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTournamentInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_info_can_be_updated(): void
    {
        $tournament = Tournament::query()->create([
            'name' => 'March Masters',
            'challonge_id' => 'marchmasters',
            'challonge_url' => 'https://challonge.com/marchmasters',
            'challonge_slug' => 'marchmasters',
            'date' => '2026-03-18',
            'participants_count' => 32,
        ]);

        $this->patchJson("/api/tournaments/{$tournament->id}", [
            'name' => 'March Masters Reloaded',
            'age_category' => 'junior',
            'event_type' => 'casual',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'March Masters Reloaded')
            ->assertJsonPath('age_category', 'junior')
            ->assertJsonPath('event_type', 'casual');

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'name' => 'March Masters Reloaded',
            'age_category' => 'junior',
            'event_type' => 'casual',
        ]);
    }

    public function test_tournament_info_update_validates_category_values(): void
    {
        $tournament = Tournament::query()->create([
            'name' => 'March Masters',
            'challonge_id' => 'marchmasters',
            'challonge_url' => 'https://challonge.com/marchmasters',
            'challonge_slug' => 'marchmasters',
            'date' => '2026-03-18',
            'participants_count' => 32,
        ]);

        $this->patchJson("/api/tournaments/{$tournament->id}", [
            'name' => 'March Masters',
            'age_category' => 'kids',
            'event_type' => 'league',
        ])->assertUnprocessable();
    }
}
