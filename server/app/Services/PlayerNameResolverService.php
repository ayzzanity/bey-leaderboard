<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerAlias;

class PlayerNameResolverService
{
    public function sanitizeImportedName(string $name): string
    {
        $cleaned = trim($name);
        $cleaned = preg_replace('/^\d+\.\s*/', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/^(.+?)\s*(\d+)$/', '$1 $2', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    public function normalizeCanonicalName(string $name, array $deckOneBases = []): string
    {
        $sanitized = $this->sanitizeImportedName($name);
        [$baseName, $suffix] = $this->splitDeckSuffix($sanitized);

        if ($suffix !== null) {
            return sprintf('%s %d', $baseName, $suffix);
        }

        if (in_array($this->aliasKey($baseName), $deckOneBases, true) || $this->hasExistingNumberedVariant($baseName)) {
            return sprintf('%s 1', $baseName);
        }

        return $baseName;
    }

    public function splitDeckSuffix(string $name): array
    {
        $sanitized = $this->sanitizeImportedName($name);

        if (preg_match('/^(.+?) (\d+)$/', $sanitized, $matches) === 1) {
            return [trim($matches[1]), (int) $matches[2]];
        }

        return [$sanitized, null];
    }

    public function collectDeckOneBases(array $names): array
    {
        $bases = [];

        foreach ($names as $name) {
            [$baseName, $suffix] = $this->splitDeckSuffix($name);

            if ($suffix !== null) {
                $bases[$this->aliasKey($baseName)] = true;
            }
        }

        return array_keys($bases);
    }

    public function aliasKey(string $name): string
    {
        $key = mb_strtolower(trim($name));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        return trim($key);
    }

    public function resolvePlayer(string $importedName, array $deckOneBases = []): Player
    {
        $sanitizedName = $this->normalizeCanonicalName($importedName, $deckOneBases);
        $rawKey = $this->aliasKey($importedName);
        $sanitizedKey = $this->aliasKey($sanitizedName);

        $alias = PlayerAlias::query()
            ->with('player')
            ->whereIn('alias_key', array_values(array_unique([$rawKey, $sanitizedKey])))
            ->first();

        if ($alias?->player) {
            return $alias->player;
        }

        $player = Player::query()->firstOrCreate([
            'name' => $sanitizedName,
        ]);

        $this->storeAlias($player, $importedName);
        $this->storeAlias($player, $sanitizedName);

        return $player;
    }

    public function storeAlias(Player $player, string $aliasName): void
    {
        $aliasName = trim($aliasName);

        if ($aliasName === '') {
            return;
        }

        PlayerAlias::query()->updateOrCreate(
            ['alias_key' => $this->aliasKey($aliasName)],
            [
                'player_id' => $player->id,
                'alias_name' => $aliasName,
            ]
        );
    }

    private function hasExistingNumberedVariant(string $baseName): bool
    {
        $baseKey = $this->aliasKey($baseName);

        foreach (Player::query()->pluck('name') as $name) {
            [$existingBase, $suffix] = $this->splitDeckSuffix($name);
            if ($suffix !== null && $this->aliasKey($existingBase) === $baseKey) {
                return true;
            }
        }

        foreach (PlayerAlias::query()->pluck('alias_name') as $aliasName) {
            [$existingBase, $suffix] = $this->splitDeckSuffix($aliasName);
            if ($suffix !== null && $this->aliasKey($existingBase) === $baseKey) {
                return true;
            }
        }

        return false;
    }
}
