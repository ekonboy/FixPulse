<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RunnerClient
{
    public function auditLighthouse(string $url, string $device, string $locale = 'es-ES'): array
    {
        $baseUrl = rtrim((string) config('services.runner.url', 'http://127.0.0.1:3333'), '/');
        $key = (string) config('services.runner.key');
        if ($key === '') {
            throw new RuntimeException('RUNNER_KEY is not configured.');
        }

        $response = Http::timeout(95)
            ->acceptJson()
            ->withHeaders([
                'X-Runner-Key' => $key,
            ])
            ->post($baseUrl.'/audit/lighthouse', [
                'url' => $url,
                'device' => $device,
                'locale' => $locale,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('Runner HTTP error: '.$response->status());
        }

        $data = $response->json();
        if (! is_array($data) || Arr::get($data, 'ok') !== true || ! is_array($data['lhr'] ?? null)) {
            $message = Arr::get($data, 'error.message', 'Invalid runner response.');
            throw new RuntimeException((string) $message);
        }

        return $data['lhr'];
    }

    public function analyzeTechnology(string $url): array
    {
        $baseUrl = rtrim((string) config('services.runner.url', 'http://127.0.0.1:3333'), '/');
        $key = (string) config('services.runner.key');
        if ($key === '') {
            throw new RuntimeException('RUNNER_KEY is not configured.');
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->withHeaders([
                'X-Runner-Key' => $key,
            ])
            ->post($baseUrl.'/analyze/tech', [
                'url' => $url,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('Runner HTTP error: '.$response->status());
        }

        $data = $response->json();
        if (! is_array($data) || Arr::get($data, 'ok') !== true || ! is_array($data['analysis'] ?? null)) {
            $message = Arr::get($data, 'error.message', 'Invalid runner response.');
            throw new RuntimeException((string) $message);
        }

        return $data['analysis'];
    }
}
