<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class IssueNormalizer
{
    private const TARGET_CATEGORIES = [
        'performance' => 'perf',
        'best-practices' => 'best_practices',
    ];

    private const TITLE_TRANSLATIONS = [
        'render-blocking-resources' => 'Recursos que bloquean el render',
        'unused-javascript' => 'JavaScript no usado',
        'unused-css-rules' => 'CSS no usado',
        'unminified-javascript' => 'JavaScript sin minificar',
        'unminified-css' => 'CSS sin minificar',
        'uses-text-compression' => 'Compresion de texto desactivada',
        'uses-long-cache-ttl' => 'Cache TTL corto en recursos estaticos',
        'uses-optimized-images' => 'Imagenes sin optimizar',
        'uses-responsive-images' => 'Imagenes no responsivas',
        'modern-image-formats' => 'Formatos de imagen modernos no usados',
        'offscreen-images' => 'Imagenes fuera de pantalla sin lazy-load',
        'server-response-time' => 'Tiempo de respuesta del servidor alto',
        'total-byte-weight' => 'Peso total de pagina alto',
        'largest-contentful-paint-element' => 'Elemento LCP lento',
        'dom-size' => 'DOM excesivo',
        'font-display' => 'Fuentes sin estrategia font-display',
        'uses-http2' => 'Servidor sin HTTP/2',
        'uses-passive-event-listeners' => 'Event listeners no pasivos',
        'no-document-write' => 'Uso de document.write detectado',
    ];

    public function normalize(array $lhr): array
    {
        $audits = Arr::get($lhr, 'audits', []);
        $categoryRefs = $this->collectAuditCategoryRefs($lhr);
        $targetHost = strtolower((string) parse_url((string) Arr::get($lhr, 'finalDisplayedUrl', ''), PHP_URL_HOST));
        $issues = [];

        foreach ($categoryRefs as $auditKey => $categoryKey) {
            $audit = Arr::get($audits, $auditKey);
            if (! is_array($audit)) {
                continue;
            }

            if (! $this->shouldIncludeAudit($audit)) {
                continue;
            }

            $issue = $this->buildIssue($auditKey, $audit, $categoryKey, $targetHost);
            if ($issue === null) {
                continue;
            }

            $issues[] = $issue;
        }

        return $issues;
    }

    private function buildIssue(string $key, array $audit, string $categoryKey, string $targetHost): ?array
    {
        $score = isset($audit['score']) && is_numeric($audit['score']) ? (float) $audit['score'] : null;
        $mode = (string) ($audit['scoreDisplayMode'] ?? 'numeric');
        $impactScore = $this->estimateImpactScore($score, $audit);
        $effortScore = $this->estimateEffort($key, $audit);

        $savingsMs = Arr::get($audit, 'details.overallSavingsMs');
        $savingsBytes = Arr::get($audit, 'details.overallSavingsBytes');
        $items = Arr::get($audit, 'details.items', []);

        $estimatedSavingMs = is_numeric($savingsMs) ? (int) max(0, round((float) $savingsMs)) : null;
        $estimatedSavingKb = is_numeric($savingsBytes) ? (int) max(0, round(((float) $savingsBytes) / 1024)) : null;

        $itemCount = is_array($items) ? count($items) : 0;
        $priority = (int) round(
            ($impactScore * 1.45)
            - ($effortScore * 0.75)
            + min(40, ($estimatedSavingMs ?? 0) / 75)
            + min(18, ($estimatedSavingKb ?? 0) / 50)
            + min(12, $itemCount / 4)
        );

        $guidance = $this->buildFixGuidance($key);
        $normalizedItems = $this->normalizeTopItems($audit, $targetHost);

        return [
            'key' => $key,
            'category' => $categoryKey,
            'title' => $this->resolveTitle($key, (string) ($audit['title'] ?? Str::headline(str_replace('-', ' ', $key)))),
            'severity' => $this->inferSeverity($impactScore),
            'impact_score' => $impactScore,
            'effort_score' => $effortScore,
            'estimated_saving_ms' => $estimatedSavingMs,
            'estimated_saving_kb' => $estimatedSavingKb,
            'priority_score' => $priority,
            'evidence_jsonb' => [
                'audit_key' => $key,
                'title_en' => $audit['title'] ?? null,
                'description_en' => $audit['description'] ?? null,
                'display_value' => $audit['displayValue'] ?? null,
                'score' => $score,
                'score_display_mode' => $mode,
                'overall_savings_ms' => $estimatedSavingMs,
                'overall_savings_kb' => $estimatedSavingKb,
                'items_count' => $itemCount,
                'top_items' => $normalizedItems,
                'details' => $this->trimDetails(Arr::get($audit, 'details')),
            ],
            'fix_jsonb' => [
                'summary' => $guidance['summary'],
                'where_to_change' => $guidance['where_to_change'],
                'steps' => $guidance['steps'],
                'validation' => $guidance['validation'],
                'lighthouse_hint' => $audit['description'] ?? null,
            ],
            'resources' => $this->extractResources($audit, $targetHost),
        ];
    }

    private function collectAuditCategoryRefs(array $lhr): array
    {
        $map = [];

        foreach (self::TARGET_CATEGORIES as $categoryId => $normalizedCategory) {
            $refs = Arr::get($lhr, "categories.$categoryId.auditRefs", []);

            if (! is_array($refs)) {
                continue;
            }

            foreach ($refs as $ref) {
                if (! is_array($ref) || empty($ref['id'])) {
                    continue;
                }

                $key = (string) $ref['id'];
                $map[$key] = $normalizedCategory;
            }
        }

        return $map;
    }

    private function shouldIncludeAudit(array $audit): bool
    {
        $mode = (string) ($audit['scoreDisplayMode'] ?? 'numeric');
        if (in_array($mode, ['notApplicable', 'manual'], true)) {
            return false;
        }

        if ($mode === 'error') {
            return true;
        }

        $score = isset($audit['score']) && is_numeric($audit['score']) ? (float) $audit['score'] : null;
        if ($score !== null && $score < 0.99) {
            return true;
        }

        $savingsMs = Arr::get($audit, 'details.overallSavingsMs');
        $savingsBytes = Arr::get($audit, 'details.overallSavingsBytes');
        if ((is_numeric($savingsMs) && (float) $savingsMs >= 50) || (is_numeric($savingsBytes) && (float) $savingsBytes >= 8192)) {
            return true;
        }

        $items = Arr::get($audit, 'details.items', []);

        return is_array($items) && count($items) > 0 && in_array($mode, ['informative', 'binary', 'numeric'], true);
    }

    private function estimateImpactScore(?float $score, array $audit): int
    {
        if ($score !== null) {
            return (int) max(0, min(100, round((1 - $score) * 100)));
        }

        $savingsMs = Arr::get($audit, 'details.overallSavingsMs');
        $savingsBytes = Arr::get($audit, 'details.overallSavingsBytes');

        $fromMs = is_numeric($savingsMs) ? (float) $savingsMs / 120 : 0;
        $fromKb = is_numeric($savingsBytes) ? ((float) $savingsBytes / 1024) / 20 : 0;

        return (int) max(15, min(85, round(max($fromMs, $fromKb))));
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
            'server-response-time', 'uses-long-cache-ttl', 'largest-contentful-paint-element', 'dom-size', 'uses-http2' => 70,
            'unused-javascript', 'unused-css-rules', 'render-blocking-resources', 'uses-responsive-images', 'uses-optimized-images' => 55,
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
            $details['items'] = array_slice($details['items'], 0, 40);
        }

        return $details;
    }

    private function resolveTitle(string $key, string $defaultTitle): string
    {
        return self::TITLE_TRANSLATIONS[$key] ?? $defaultTitle;
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

    private function extractResources(array $audit, string $targetHost): array
    {
        $resources = [];
        $items = Arr::get($audit, 'details.items', []);
        $seen = [];

        if (! is_array($items)) {
            return $resources;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = $this->extractItemUrl($item);
            if ($url === null) {
                continue;
            }

            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $transferBytes = $this->resolveTransferBytes($item);
            $wastedBytes = $this->resolveWastedBytes($item);
            $resourceHost = strtolower((string) parse_url($url, PHP_URL_HOST));

            $resources[] = [
                'resource_type' => $this->inferResourceType($url),
                'url' => $url,
                'transfer_size_kb' => is_numeric($transferBytes) ? (int) max(0, round(((float) $transferBytes) / 1024)) : null,
                'details_jsonb' => [
                    'url' => $url,
                    'host' => $resourceHost,
                    'is_third_party' => $this->isThirdParty($resourceHost, $targetHost),
                    'selector' => $this->extractItemSelector($item),
                    'snippet' => Arr::get($item, 'node.snippet') ?? Arr::get($item, 'snippet'),
                    'wasted_kb' => is_numeric($wastedBytes) ? (int) max(0, round(((float) $wastedBytes) / 1024)) : null,
                    'savings_ms' => is_numeric(Arr::get($item, 'wastedMs')) ? (int) round((float) Arr::get($item, 'wastedMs')) : null,
                    'display' => Arr::get($item, 'label') ?? Arr::get($item, 'description') ?? Arr::get($item, 'name'),
                    'raw' => $item,
                ],
            ];
        }

        return $resources;
    }

    private function normalizeTopItems(array $audit, string $targetHost): array
    {
        $items = Arr::get($audit, 'details.items', []);
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach (array_slice($items, 0, 12) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = $this->extractItemUrl($item);
            $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
            $transferBytes = $this->resolveTransferBytes($item);
            $wastedBytes = $this->resolveWastedBytes($item);

            $normalized[] = [
                'url' => $url,
                'host' => $host !== '' ? $host : null,
                'type' => $url ? $this->inferResourceType($url) : 'other',
                'selector' => $this->extractItemSelector($item),
                'is_third_party' => $host !== '' ? $this->isThirdParty($host, $targetHost) : false,
                'transfer_kb' => is_numeric($transferBytes) ? (int) max(0, round(((float) $transferBytes) / 1024)) : null,
                'wasted_kb' => is_numeric($wastedBytes) ? (int) max(0, round(((float) $wastedBytes) / 1024)) : null,
                'savings_ms' => is_numeric(Arr::get($item, 'wastedMs')) ? (int) round((float) Arr::get($item, 'wastedMs')) : null,
                'label' => Arr::get($item, 'label') ?? Arr::get($item, 'description') ?? Arr::get($item, 'name'),
            ];
        }

        return $normalized;
    }

    private function resolveTransferBytes(array $item): ?float
    {
        foreach (['totalBytes', 'transferSize', 'resourceSize', 'totalByteWeight'] as $key) {
            $value = Arr::get($item, $key);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function resolveWastedBytes(array $item): ?float
    {
        foreach (['wastedBytes', 'wastedByteCount'] as $key) {
            $value = Arr::get($item, $key);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function extractItemUrl(array $item): ?string
    {
        foreach (['url', 'resourceUrl', 'sourceURL', 'sourceUrl', 'documentURL'] as $candidate) {
            $url = Arr::get($item, $candidate);
            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $trimmed = trim($url);
            if (! str_starts_with($trimmed, 'http')) {
                continue;
            }

            return $trimmed;
        }

        return null;
    }

    private function extractItemSelector(array $item): ?string
    {
        foreach (['node.selector', 'node.path', 'selector'] as $candidate) {
            $value = Arr::get($item, $candidate);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function isThirdParty(string $resourceHost, string $targetHost): bool
    {
        if ($resourceHost === '' || $targetHost === '') {
            return false;
        }

        if ($resourceHost === $targetHost) {
            return false;
        }

        return ! Str::endsWith($resourceHost, '.'.$targetHost);
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
