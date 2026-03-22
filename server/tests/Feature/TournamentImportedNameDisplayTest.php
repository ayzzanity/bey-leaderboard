<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentImportedNameDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_standings_keep_imported_name_alongside_canonical_name(): void
    {
        $canonical = Player::query()->create(['name' => 'AyzEyesIce 1']);

        $tournament = Tournament::query()->create([
            'name' => 'Spring Event',
            'challonge_id' => 'spring-event',
            'challonge_url' => 'https://challonge.com/spring-event',
            'challonge_slug' => 'spring-event',
            'date' => '2026-03-20',
            'participants_count' => 1,
        ]);

        TournamentPlayer::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $canonical->id,
            'imported_name' => 'Ayz 1',
            'seed' => 1,
            'swiss_wins' => 1,
            'swiss_losses' => 0,
            'swiss_rank' => 1,
            'buchholz_score' => 0,
        ]);

        $this->getJson("/api/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJsonPath('swiss_standings.0.alias_name', 'Ayz 1')
            ->assertJsonPath('swiss_standings.0.display_name', 'Ayz 1 (AyzEyesIce 1)');
    }
}
