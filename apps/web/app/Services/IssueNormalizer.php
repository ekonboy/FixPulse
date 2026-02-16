<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class IssueNormalizer
{
    private const AUDIT_KEYS = [
        'render-blocking-resources',
        'unused-css-rules',
        'unused-javascript',
        'unminified-css',
        'unminified-javascript',
        'modern-image-formats',
        'offscreen-images',
        'uses-optimized-images',
        'uses-responsive-images',
        'server-response-time',
        'uses-text-compression',
        'dom-size',
        'largest-contentful-paint-element',
        'total-byte-weight',
        'font-display',
        'uses-long-cache-ttl',
        'color-contrast',
        'meta-description',
        'image-alt',
        'is-on-https',
    ];

    public function normalize(array $lhr): array
    {
        $audits = Arr::get($lhr, 'audits', []);
        $issues = [];

        foreach (self::AUDIT_KEYS as $auditKey) {
            $audit = Arr::get($audits, $auditKey);
            if (! is_array($audit)) {
                continue;
            }

            $issue = $this->buildIssue($auditKey, $audit);
            if ($issue === null) {
                continue;
            }

            $issues[] = $issue;
        }

        return $issues;
    }

    private function buildIssue(string $key, array $audit): ?array
    {
        $score = (float) ($audit['score'] ?? 1.0);
        $mode = (string) ($audit['scoreDisplayMode'] ?? 'numeric');

        if (in_array($mode, ['notApplicable', 'manual'], true) || $score >= 0.99) {
            return null;
        }

        $impactScore = (int) max(0, min(100, round((1 - $score) * 100)));
        $effortScore = $this->estimateEffort($key, $audit);

        $savingsMs = Arr::get($audit, 'details.overallSavingsMs');
        $savingsBytes = Arr::get($audit, 'details.overallSavingsBytes');

        $estimatedSavingMs = is_numeric($savingsMs) ? (int) max(0, round((float) $savingsMs)) : null;
        $estimatedSavingKb = is_numeric($savingsBytes) ? (int) max(0, round(((float) $savingsBytes) / 1024)) : null;

        $priority = (int) round(($impactScore * 1.4) - ($effortScore * 0.8) + min(25, ($estimatedSavingMs ?? 0) / 100));

        $guidance = $this->buildFixGuidance($key);

        return [
            'key' => $key,
            'category' => $this->inferCategory($audit),
            'title' => (string) ($audit['title'] ?? Str::headline(str_replace('-', ' ', $key))),
            'severity' => $this->inferSeverity($impactScore),
            'impact_score' => $impactScore,
            'effort_score' => $effortScore,
            'estimated_saving_ms' => $estimatedSavingMs,
            'estimated_saving_kb' => $estimatedSavingKb,
            'priority_score' => $priority,
            'evidence_jsonb' => [
                'description' => $audit['description'] ?? null,
                'display_value' => $audit['displayValue'] ?? null,
                'score' => $score,
                'details' => $this->trimDetails(Arr::get($audit, 'details')),
            ],
            'fix_jsonb' => [
                'summary' => $guidance['summary'],
                'where_to_change' => $guidance['where_to_change'],
                'steps' => $guidance['steps'],
                'validation' => $guidance['validation'],
                'lighthouse_hint' => $audit['description'] ?? null,
            ],
            'resources' => $this->extractResources($audit),
        ];
    }

    private function inferCategory(array $audit): string
    {
        $group = (string) Arr::get($audit, 'group', 'performance');

        return match ($group) {
            'seo' => 'seo',
            'a11y' => 'a11y',
            'best-practices' => 'best_practices',
            default => 'perf',
        };
    }

    private function inferSeverity(int $impactScore): string
    {
        return match (true) {
            $impactScore >= 80 => 'critical',
            $impactScore >= 60 => 'high',
            $impactScore >= 40 => 'med',
            $impactScore >= 20 => 'low',
            default => 'info',
        };
    }

    private function estimateEffort(string $key, array $audit): int
    {
        $base = match ($key) {
            'server-response-time', 'uses-long-cache-ttl', 'largest-contentful-paint-element', 'dom-size' => 70,
            'unused-javascript', 'unused-css-rules', 'render-blocking-resources' => 55,
            'unminified-css', 'unminified-javascript', 'uses-text-compression', 'font-display' => 30,
            default => 45,
        };

        $items = Arr::get($audit, 'details.items');
        if (is_array($items)) {
            $base += min(20, (int) floor(count($items) / 5));
        }

        return max(10, min(100, $base));
    }

    private function trimDetails(mixed $details): mixed
    {
        if (! is_array($details)) {
            return $details;
        }

        if (isset($details['items']) && is_array($details['items'])) {
            $details['items'] = array_slice($details['items'], 0, 15);
        }

        return $details;
    }

    private function buildFixSummary(string $key): string
    {
        return match ($key) {
            'unused-javascript' => 'Split JS bundles and defer non-critical scripts.',
            'unused-css-rules' => 'Remove unused CSS and load route-specific styles only.',
            'render-blocking-resources' => 'Inline critical CSS and defer non-critical CSS/JS.',
            'server-response-time' => 'Reduce backend latency with caching and query optimization.',
            'uses-text-compression' => 'Enable Brotli/Gzip for text assets at the web server level.',
            default => 'Apply the Lighthouse recommendation and re-run the scan to verify impact.',
        };
    }

    private function buildFixGuidance(string $key): array
    {
        return match ($key) {
            'render-blocking-resources' => [
                'summary' => 'Reduce resources that block first paint.',
                'where_to_change' => 'Frontend entry points (Blade layout, Vite bundles, critical CSS strategy).',
                'steps' => [
                    'Inline only critical CSS required for the first viewport.',
                    'Move non-critical CSS/JS to deferred loading.',
                    'Add preconnect/preload for critical fonts and key stylesheets.',
                ],
                'validation' => 'Re-run scan and verify lower FCP/LCP plus fewer render-blocking resources.',
            ],
            'unused-javascript' => [
                'summary' => 'Ship less JavaScript on initial load.',
                'where_to_change' => 'JS build config and page-specific frontend modules.',
                'steps' => [
                    'Split bundles by route or feature.',
                    'Lazy-load heavy modules after first interaction.',
                    'Remove third-party scripts not used in the current page.',
                ],
                'validation' => 'Re-run scan and verify reduced unused JS and lower transfer size.',
            ],
            'unused-css-rules' => [
                'summary' => 'Remove CSS rules not used by the page.',
                'where_to_change' => 'Tailwind/CSS build pipeline and component stylesheets.',
                'steps' => [
                    'Enable CSS purge/tree-shaking for production.',
                    'Split global CSS into page/component scopes.',
                    'Delete old classes no longer used by templates.',
                ],
                'validation' => 'Re-run scan and verify reduced unused CSS and better render metrics.',
            ],
            'uses-text-compression' => [
                'summary' => 'Enable Brotli or Gzip for text assets.',
                'where_to_change' => 'Nginx/Apache compression config in server layer.',
                'steps' => [
                    'Enable Brotli or Gzip modules in web server.',
                    'Compress text/html, text/css, application/javascript, application/json.',
                    'Avoid compressing already compressed binary formats.',
                ],
                'validation' => 'Check response headers and re-run scan to verify transfer savings.',
            ],
            'server-response-time' => [
                'summary' => 'Lower backend response time for initial HTML.',
                'where_to_change' => 'Laravel controllers, DB queries, caching strategy, VPS resources.',
                'steps' => [
                    'Profile slow queries and add indexes where needed.',
                    'Cache expensive responses/fragments.',
                    'Reduce middleware/work executed before first byte.',
                ],
                'validation' => 'Re-run scan and verify lower TTFB and improved performance score.',
            ],
            'uses-long-cache-ttl' => [
                'summary' => 'Increase cache lifetime for static assets.',
                'where_to_change' => 'Nginx static asset cache headers and Vite hashed files.',
                'steps' => [
                    'Serve assets with long max-age and immutable when hashed.',
                    'Use content-hash filenames for JS/CSS/images.',
                    'Keep short cache for HTML documents.',
                ],
                'validation' => 'Re-run scan and verify fewer cache policy warnings.',
            ],
            default => [
                'summary' => $this->buildFixSummary($key),
                'where_to_change' => 'Frontend templates/assets and web server configuration depending on the issue.',
                'steps' => [
                    'Open the related issue evidence and inspect affected resources.',
                    'Apply the recommended optimization in code or server config.',
                    'Deploy and run a new scan for the same URL.',
                ],
                'validation' => 'Confirm improvement in issue score and summary metrics on next scan.',
            ],
        };
    }

    private function extractResources(array $audit): array
    {
        $resources = [];
        $items = Arr::get($audit, 'details.items', []);

        if (! is_array($items)) {
            return $resources;
        }

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['url'])) {
                continue;
            }

            $url = (string) $item['url'];
            $resources[] = [
                'resource_type' => $this->inferResourceType($url),
                'url' => $url,
                'transfer_size_kb' => isset($item['totalBytes']) ? (int) max(0, round(((int) $item['totalBytes']) / 1024)) : null,
                'details_jsonb' => $item,
            ];
        }

        return $resources;
    }

    private function inferResourceType(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (Str::endsWith($path, '.js')) {
            return 'scripts';
        }

        if (Str::endsWith($path, '.css')) {
            return 'styles';
        }

        if (Str::endsWith($path, ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.avif', '.svg'])) {
            return 'images';
        }

        if (Str::endsWith($path, ['.woff', '.woff2', '.ttf', '.otf'])) {
            return 'fonts';
        }

        return 'other';
    }
}
