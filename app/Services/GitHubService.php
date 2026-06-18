<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GitHubService
{
    private string $token;
    private string $repo;

    public function __construct()
    {
        $this->token = config('services.github.token', '');
        $this->repo  = config('services.github.repo', '');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->repo !== '';
    }

    /** Creates a GitHub issue and returns ['number', 'url']. */
    public function createIssue(string $title, string $body, string $label): array
    {
        $response = Http::withToken($this->token)
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->post("https://api.github.com/repos/{$this->repo}/issues", [
                'title'  => $title,
                'body'   => $body,
                'labels' => [$label],
            ]);

        $response->throw();

        return [
            'number' => $response->json('number'),
            'url'    => $response->json('html_url'),
        ];
    }

    public function issuesUrl(): string
    {
        return "https://github.com/{$this->repo}/issues";
    }
}
