<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ChallongeService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.challonge.key');
    }

    public function getTournament($slug)
    {
        return Http::connectTimeout(10)->timeout(30)->get(
            "https://api.challonge.com/v1/tournaments/$slug.json",
            [
                'api_key' => $this->apiKey
            ]
        )->json();
    }

    public function getParticipants($slug)
    {
        return Http::connectTimeout(10)->timeout(30)->get(
            "https://api.challonge.com/v1/tournaments/$slug/participants.json",
            [
                'api_key' => $this->apiKey
            ]
        )->json();
    }

    public function getMatches($slug)
    {
        return Http::connectTimeout(10)->timeout(30)->get(
            "https://api.challonge.com/v1/tournaments/$slug/matches.json",
            [
                'api_key' => $this->apiKey
            ]
        )->json();
    }
}
