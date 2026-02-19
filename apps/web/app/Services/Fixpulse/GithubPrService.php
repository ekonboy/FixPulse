<?php

namespace App\Services\Fixpulse;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubPrService
{
    public function createPullRequest(string $repo, string $title, string $body, string $head, string $base): array
    {
        $token = (string) config('services.github.token', '');
        if ($token === '') {
            throw new RuntimeException('GITHUB_TOKEN is required to create PRs.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'FixPulse/1.0',
            ])
            ->post("https://api.github.com/repos/{$repo}/pulls", [
                'title' => $title,
                'body' => $body,
                'head' => $head,
                'base' => $base,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('GitHub PR API error: '.$response->status().' '.$response->body());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Invalid GitHub PR response.');
        }

        return [
            'url' => (string) ($data['html_url'] ?? ''),
            'number' => isset($data['number']) ? (int) $data['number'] : null,
        ];
    }
}

