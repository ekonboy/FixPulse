<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TechnologyLookupService
{
    public function resolvePrimary(string $url): array
    {
        $apiKey = (string) config('services.whatcms.key');

        if ($apiKey !== '') {
            return $this->resolveWithWhatCms($url, $apiKey);
        }

        return $this->resolveWithLocalFallback($url);
    }

    private function resolveWithWhatCms(string $url, string $apiKey): array
    {
        $endpoint = (string) config('services.whatcms.endpoint', 'https://whatcms.org/API/Tech');
        $privateRaw = config('services.whatcms.private', true);
        $private = filter_var($privateRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($private === null) {
            $private = true;
        }

        $response = Http::timeout(25)
            ->acceptJson()
            ->get($endpoint, [
                'key' => $apiKey,
                'url' => $url,
                'private' => $private ? 'true' : 'false',
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('WhatCMS HTTP error: '.$response->status());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Invalid WhatCMS response format.');
        }

        $code = (int) Arr::get($data, 'result.code', 0);
        $msg = (string) Arr::get($data, 'result.msg', 'Unknown WhatCMS error.');

        if ($code !== 200) {
            throw new RuntimeException("WhatCMS error {$code}: {$msg}");
        }

        $results = Arr::get($data, 'results', []);
        if (! is_array($results) || $results === []) {
            return [
                'source' => 'whatcms',
                'primary' => null,
                'summary' => 'No technology detected by WhatCMS.',
                'technologies' => [],
            ];
        }

        $normalized = collect($results)
            ->filter(fn ($row) => is_array($row) && ! empty($row['name']))
            ->map(function (array $row): array {
                $categories = Arr::get($row, 'categories', []);
                if (! is_array($categories)) {
                    $categories = [];
                }

                return [
                    'name' => (string) $row['name'],
                    'categories' => $categories,
                    'version' => isset($row['version']) ? (string) $row['version'] : null,
                    'score' => $this->categoryScore($categories),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();

        $primary = $normalized[0] ?? null;

        return [
            'source' => 'whatcms',
            'primary' => $primary,
            'summary' => $primary
                ? 'Primary technology detected: '.$primary['name']
                : 'No primary technology detected.',
            'technologies' => $normalized,
        ];
    }

    private function resolveWithLocalFallback(string $url): array
    {
        $analysis = app(RunnerClient::class)->analyzeTechnology($url);
        $list = Arr::get($analysis, 'technologies', []);

        if (! is_array($list)) {
            $list = [];
        }

        $normalized = collect($list)
            ->filter(fn ($row) => is_array($row) && ! empty($row['name']))
            ->map(function (array $row): array {
                $category = Arr::get($row, 'category');
                $categories = $category ? [(string) $category] : [];

                return [
                    'name' => (string) $row['name'],
                    'categories' => $categories,
                    'version' => null,
                    'score' => $this->categoryScore($categories),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();

        $primary = $normalized[0] ?? null;

        return [
            'source' => 'local_fallback',
            'primary' => $primary,
            'summary' => $primary
                ? 'Primary technology (fallback) detected: '.$primary['name']
                : 'No technology detected. Configure WHATCMS_API_KEY for better results.',
            'technologies' => $normalized,
        ];
    }

    private function categoryScore(array $categories): int
    {
        $labels = collect($categories)
            ->map(fn ($v) => strtolower((string) $v))
            ->all();

        foreach ($labels as $label) {
            if (str_contains($label, 'cms')) {
                return 100;
            }

            if (str_contains($label, 'ecommerce')) {
                return 95;
            }

            if (str_contains($label, 'framework')) {
                return 90;
            }

            if (str_contains($label, 'site generator')) {
                return 88;
            }
        }

        foreach ($labels as $label) {
            if (str_contains($label, 'programming language')) {
                return 60;
            }

            if (str_contains($label, 'web server')) {
                return 50;
            }

            if (str_contains($label, 'database')) {
                return 40;
            }
        }

        return 30;
    }
}
