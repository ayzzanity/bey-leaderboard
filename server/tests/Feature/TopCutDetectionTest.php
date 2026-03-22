<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopCutDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_cut_uses_main_elimination_round_not_all_single_elim_participants(): void
    {
        $players = collect(range(1, 10))->mapWithKeys(function ($number) {
            $player = Player::query()->create(['name' => "Player {$number}"]);

            return [$number => $player];
        });

        $tournament = Tournament::query()->create([
            'name' => 'Top 6 Test',
            'challonge_id' => 'top6',
            'challonge_url' => 'https://challonge.com/top6',
            'challonge_slug' => 'top6',
            'date' => '2026-03-20',
            'participants_count' => 10,
        ]);

        foreach (range(1, 10) as $seed) {
            TournamentPlayer::query()->create([
                'tournament_id' => $tournament->id,
                'player_id' => $players[$seed]->id,
                'seed' => $seed,
                'swiss_wins' => 10 - $seed,
                'swiss_losses' => max(0, $seed - 1),
                'swiss_rank' => $seed,
                'buchholz_score' => 20 - $seed,
            ]);
        }

        foreach (range(1, 10) as $placement) {
            TournamentResult::query()->create([
                'tournament_id' => $tournament->id,
                'player_id' => $players[$placement]->id,
                'placement' => $placement,
                'points_awarded' => match ($placement) {
                    1 => 10,
                    2 => 7,
                    3, 4 => 5,
                    default => 0,
                },
            ]);
        }

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[7]->id,
            'player2_id' => $players[10]->id,
            'winner_id' => $players[7]->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[8]->id,
            'player2_id' => $players[9]->id,
            'winner_id' => $players[8]->id,
            'round' => 1,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[1]->id,
            'player2_id' => $players[7]->id,
            'winner_id' => $players[1]->id,
            'round' => 2,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[2]->id,
            'player2_id' => $players[6]->id,
            'winner_id' => $players[2]->id,
            'round' => 2,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[3]->id,
            'player2_id' => $players[8]->id,
            'winner_id' => $players[3]->id,
            'round' => 2,
            'stage' => 'single_elim',
        ]);

        MatchResult::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[4]->id,
            'player2_id' => $players[5]->id,
            'winner_id' => $players[4]->id,
            'round' => 2,
            'stage' => 'single_elim',
        ]);

        $response = $this->getJson("/api/tournaments/{$tournament->id}")
            ->assertOk();

        $topCutNames = collect($response->json('top_cut_standings'))->pluck('player.name')->all();
        $qualifiedNames = collect($response->json('swiss_standings'))
            ->filter(fn ($row) => $row['qualified_for_top_cut'])
            ->pluck('player.name')
            ->all();

        $this->assertSame(
            ['Player 1', 'Player 2', 'Player 3', 'Player 4', 'Player 5', 'Player 6', 'Player 7', 'Player 8'],
            $topCutNames
        );

        $this->assertSame($topCutNames, $qualifiedNames);
        $this->assertNotContains('Player 9', $topCutNames);
        $this->assertNotContains('Player 10', $topCutNames);
    }
}
