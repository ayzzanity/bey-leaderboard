<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ChallongeStandingsService
{
    public function getGroupStandings(string $slug): ?array
    {
        try {
            $response = Http::get("https://challonge.com/{$slug}/standings");
        } catch (ConnectionException) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        return $this->parseGroupStandingsHtml($response->body());
    }

    public function parseGroupStandingsHtml(string $html): ?array
    {
        libxml_use_internal_errors(true);

        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $xpath = new \DOMXPath($document);

        foreach ($xpath->query('//table') as $table) {
            $headers = [];

            foreach ($xpath->query('.//th', $table) as $headerCell) {
                $headers[] = $this->normalizeHeader($headerCell->textContent);
            }

            if (!$this->isStandingsTable($headers)) {
                continue;
            }

            $rows = [];
            $rowNodes = $xpath->query('.//tbody/tr', $table);

            foreach ($rowNodes as $rowNode) {
                $cells = $xpath->query('./td', $rowNode);

                if ($cells->length === 0) {
                    continue;
                }

                $data = [];

                foreach ($headers as $index => $header) {
                    $cell = $cells->item($index);
                    $data[$header] = $cell ? trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '') : null;
                }

                $participantText = $data['participant'] ?? '';
                $advanced = stripos($participantText, 'Advanced') !== false;
                $participantName = trim(preg_replace('/^Advanced\s+/i', '', $participantText) ?? $participantText);
                [$wins, $losses, $ties] = $this->parseRecord($data['match w-l-t'] ?? '');

                $historyIndex = $this->findHeaderIndex($headers, 'match history');
                $matchHistoryCell = $historyIndex >= 0 ? $cells->item($historyIndex) : null;

                $rows[] = [
                    'rank' => $this->toInt($data['rank'] ?? null),
                    'participant_name' => $participantName,
                    'qualified_for_top_cut' => $advanced,
                    'wins' => $wins,
                    'losses' => $losses,
                    'ties' => $ties,
                    'score' => $this->toFloat($data['score'] ?? null),
                    'tb' => $this->toInt($data['tb'] ?? null),
                    'pts' => $this->toInt($data['pts'] ?? null),
                    'buchholz' => $this->toFloat($data['buchholz'] ?? null),
                    'points_diff' => $this->toInt($data['pts diff'] ?? null),
                    'match_history' => $this->parseMatchHistory($matchHistoryCell?->textContent ?? ''),
                ];
            }

            if (!empty($rows)) {
                return $rows;
            }
        }

        return null;
    }

    private function isStandingsTable(array $headers): bool
    {
        return in_array('rank', $headers, true)
            && in_array('participant', $headers, true)
            && in_array('score', $headers, true)
            && (in_array('pts', $headers, true) || in_array('tb', $headers, true) || in_array('buchholz', $headers, true));
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/^\d+\.\s*/', '', $header) ?? $header;
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;
        $header = str_replace(['(', ')'], '', $header);

        if (str_contains($header, 'participant')) {
            return 'participant';
        }

        if (str_contains($header, 'match w-l-t')) {
            return 'match w-l-t';
        }

        if (str_contains($header, 'match history')) {
            return 'match history';
        }

        if (str_contains($header, 'pts diff')) {
            return 'pts diff';
        }

        if (str_contains($header, 'buchholz')) {
            return 'buchholz';
        }

        if ($header === 'pts') {
            return 'pts';
        }

        if ($header === 'tb') {
            return 'tb';
        }

        if ($header === 'score') {
            return 'score';
        }

        if ($header === 'rank') {
            return 'rank';
        }

        return $header;
    }

    private function findHeaderIndex(array $headers, string $name): int
    {
        foreach ($headers as $index => $header) {
            if ($header === $name) {
                return $index;
            }
        }

        return -1;
    }

    private function parseRecord(string $record): array
    {
        if (preg_match('/(\d+)\s*-\s*(\d+)\s*-\s*(\d+)/', $record, $matches) !== 1) {
            return [0, 0, 0];
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }

    private function parseMatchHistory(string $history): array
    {
        preg_match_all('/[WLT]/i', $history, $matches);

        return array_map('strtoupper', $matches[0] ?? []);
    }

    private function toInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) preg_replace('/[^0-9\-]/', '', $value);
    }

    private function toFloat(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) preg_replace('/[^0-9.\-]/', '', $value);
    }
}
