<?php

namespace App\Services\Fixpulse;

use App\Models\Issue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AutoFixService
{
    public function __construct(private readonly GitRepositoryService $git)
    {
    }

    public function apply(Issue $issue, string $repoPath): array
    {
        $enabled = (bool) config('fixpulse_actions.autofix.enabled', true);
        $allowedKeys = (array) config('fixpulse_actions.autofix.issue_keys', []);
        $maxFiles = (int) config('fixpulse_actions.autofix.max_files_per_action', 8);
        $key = (string) $issue->key;

        if (! $enabled || ! in_array($key, $allowedKeys, true)) {
            return ['changed_files' => [], 'notes' => ['autofix_skipped']];
        }

        $candidates = $this->candidateFiles($repoPath);
        $changedFiles = [];

        foreach ($candidates as $relativePath) {
            if (count($changedFiles) >= $maxFiles) {
                break;
            }

            $this->git->guardPathAllowed($relativePath);
            $absolutePath = $repoPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (! is_file($absolutePath)) {
                continue;
            }

            $original = (string) File::get($absolutePath);
            $updated = $this->applyForIssue($key, $original);

            if ($updated === null || $updated === $original) {
                continue;
            }

            File::put($absolutePath, $updated);
            $changedFiles[] = $relativePath;
        }

        return [
            'changed_files' => $changedFiles,
            'notes' => $changedFiles === [] ? ['no_safe_change_found'] : ['safe_autofix_applied'],
        ];
    }

    private function applyForIssue(string $issueKey, string $content): ?string
    {
        return match ($issueKey) {
            'offscreen-images' => $this->addLazyLoadingToImages($content),
            'render-blocking-resources' => $this->addDeferToLocalScripts($content),
            'uses-text-compression' => $this->addCompressionHintComment($content),
            'font-display' => $this->addFontDisplaySwapHint($content),
            default => null,
        };
    }

    private function addLazyLoadingToImages(string $content): string
    {
        $index = 0;

        return (string) preg_replace_callback('/<img\b[^>]*>/i', function (array $matches) use (&$index): string {
            $tag = $matches[0];
            $index++;

            // Keep first image unchanged to avoid harming potential LCP element.
            if ($index === 1) {
                return $tag;
            }

            if (stripos($tag, 'loading=') === false) {
                $tag = rtrim(substr($tag, 0, -1)).' loading="lazy">';
            }

            if (stripos($tag, 'decoding=') === false) {
                $tag = rtrim(substr($tag, 0, -1)).' decoding="async">';
            }

            return $tag;
        }, $content) ?? $content;
    }

    private function addDeferToLocalScripts(string $content): string
    {
        return (string) preg_replace_callback('/<script\b([^>]*)><\/script>/i', function (array $matches): string {
            $attrs = $matches[1];
            $full = $matches[0];

            if (! preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $srcMatch)) {
                return $full;
            }

            $src = trim($srcMatch[1]);
            $isLocal = ! Str::startsWith($src, ['http://', 'https://', '//']);
            if (! $isLocal) {
                return $full;
            }

            if (preg_match('/\b(async|defer)\b/i', $attrs) || preg_match('/\btype\s*=\s*["\']module["\']/i', $attrs)) {
                return $full;
            }

            $attrs .= ' defer';

            return '<script'.$attrs.'></script>';
        }, $content) ?? $content;
    }

    private function addCompressionHintComment(string $content): string
    {
        $hint = '<!-- fixpulse: ensure gzip/brotli enabled at web server -->';
        if (Str::contains($content, 'fixpulse: ensure gzip/brotli')) {
            return $content;
        }

        return $hint."\n".$content;
    }

    private function addFontDisplaySwapHint(string $content): string
    {
        if (! Str::contains($content, '@font-face') || Str::contains($content, 'font-display:')) {
            return $content;
        }

        return (string) preg_replace('/@font-face\s*{([^}]*)}/i', '@font-face {$1 font-display: swap;}', $content) ?? $content;
    }

    private function candidateFiles(string $repoPath): array
    {
        $all = [];
        $patterns = [
            'src/**/*.astro',
            'src/**/*.html',
            'src/**/*.css',
            'public/**/*.html',
            'resources/**/*.blade.php',
            'resources/**/*.css',
        ];

        foreach ($patterns as $pattern) {
            $glob = glob($repoPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $pattern), GLOB_BRACE);
            if (! is_array($glob)) {
                continue;
            }

            foreach ($glob as $file) {
                if (! is_file($file)) {
                    continue;
                }

                $relative = str_replace('\\', '/', Str::after($file, rtrim($repoPath, '\\/').DIRECTORY_SEPARATOR));
                $all[$relative] = true;
            }
        }

        return array_keys($all);
    }
}

