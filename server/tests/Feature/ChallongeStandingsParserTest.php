<?php

namespace Tests\Feature;

use App\Services\ChallongeStandingsService;
use Tests\TestCase;

class ChallongeStandingsParserTest extends TestCase
{
    public function test_parser_accepts_swiss_standings_without_match_history_column(): void
    {
        $html = <<<'HTML'
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Participant</th>
                    <th>Match W-L-T</th>
                    <th>1. Score</th>
                    <th>2. Pts</th>
                    <th>3. Pts Diff</th>
                    <th>4. TB</th>
                    <th>Buchholz</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Archer Rojas</td>
                    <td>5 - 0 - 0</td>
                    <td>5.0</td>
                    <td>25</td>
                    <td>+20</td>
                    <td>0</td>
                    <td>11.0</td>
                </tr>
            </tbody>
        </table>
        HTML;

        $rows = app(ChallongeStandingsService::class)->parseGroupStandingsHtml($html);

        $this->assertNotNull($rows);
        $this->assertSame(1, $rows[0]['rank']);
        $this->assertSame('Archer Rojas', $rows[0]['participant_name']);
        $this->assertSame(5.0, $rows[0]['score']);
        $this->assertSame(25, $rows[0]['pts']);
        $this->assertSame(20, $rows[0]['points_diff']);
        $this->assertSame(0, $rows[0]['tb']);
        $this->assertSame(11.0, $rows[0]['buchholz']);
        $this->assertSame([], $rows[0]['match_history']);
    }
}
