<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Services\SwissService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwissRankingTiebreakTest extends TestCase
{
    use RefreshDatabase;

    public function test_swiss_ranking_uses_score_then_tb_then_points_then_buchholz(): void
    {
        $players = collect([
            'A', 'B', 'C', 'D',
        ])->mapWithKeys(function ($name, $index) {
            $player = Player::query()->create(['name' => $name]);

            return [$name => ['model' => $player, 'seed' => $index + 1]];
        });

        $tournament = Tournament::query()->create([
            'name' => 'Swiss Tiebreak Test',
            'challonge_id' => 'swiss-tb',
            'challonge_url' => 'https://challonge.com/swiss-tb',
            'challonge_slug' => 'swiss-tb',
            'date' => '2026-03-20',
            'participants_count' => 4,
        ]);

        foreach ($players as $entry) {
            TournamentPlayer::query()->create([
                'tournament_id' => $tournament->id,
                'player_id' => $entry['model']->id,
                'seed' => $entry['seed'],
            ]);
        }

        $this->addSwissMatch($tournament->id, $players['A']['model']->id, $players['B']['model']->id, $players['A']['model']->id, 5, 4, 1);
        $this->addSwissMatch($tournament->id, $players['C']['model']->id, $players['D']['model']->id, $players['C']['model']->id, 6, 2, 1);
        $this->addSwissMatch($tournament->id, $players['A']['model']->id, $players['D']['model']->id, $players['D']['model']->id, 3, 5, 2);
        $this->addSwissMatch($tournament->id, $players['B']['model']->id, $players['C']['model']->id, $players['B']['model']->id, 6, 4, 2);
        $this->addSwissMatch($tournament->id, $players['A']['model']->id, $players['C']['model']->id, $players['A']['model']->id, 4, 3, 3);
        $this->addSwissMatch($tournament->id, $players['B']['model']->id, $players['D']['model']->id, $players['B']['model']->id, 5, 1, 3);

        app(SwissService::class)->calculate($tournament->id);

        $standings = TournamentPlayer::query()
            ->with('player')
            ->where('tournament_id', $tournament->id)
            ->orderBy('swiss_rank')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->player->name,
                'rank' => $row->swiss_rank,
                'buchholz' => $row->buchholz_score,
            ])
            ->all();

        $rankByName = collect($standings)->mapWithKeys(fn ($row) => [$row['name'] => $row['rank']])->all();

        $this->assertLessThan($rankByName['B'], $rankByName['A']);
        $this->assertLessThan($rankByName['D'], $rankByName['C']);
    }

    private function addSwissMatch(
        int $tournamentId,
        int $player1Id,
        int $player2Id,
        int $winnerId,
        int $player1Score,
        int $player2Score,
        int $round
    ): void {
        MatchResult::query()->create([
            'tournament_id' => $tournamentId,
            'player1_id' => $player1Id,
            'player2_id' => $player2Id,
            'winner_id' => $winnerId,
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
            'round' => $round,
            'stage' => 'swiss',
        ]);
    }
}
