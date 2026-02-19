<?php

namespace App\Services\Fixpulse;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class GitRepositoryService
{
    public function repoPath(): string
    {
        $path = (string) config('fixpulse_actions.git.repo_path');
        if ($path === '' || ! is_dir($path)) {
            throw new RuntimeException('FIXPULSE_REPO_PATH is invalid or does not exist.');
        }

        return $path;
    }

    public function defaultBranch(): string
    {
        return (string) config('fixpulse_actions.git.default_branch', 'main');
    }

    public function assertRepository(string $repoPath): void
    {
        $this->run($repoPath, ['git', 'rev-parse', '--is-inside-work-tree']);
    }

    public function checkoutDefaultBranch(string $repoPath): void
    {
        $default = $this->defaultBranch();
        $this->run($repoPath, ['git', 'checkout', $default]);
        // Pull failure should not block proposal pipelines on disconnected envs.
        try {
            $this->run($repoPath, ['git', 'pull', '--ff-only', 'origin', $default]);
        } catch (\Throwable) {
        }
    }

    public function createOrResetBranch(string $repoPath, string $branch): void
    {
        $this->run($repoPath, ['git', 'checkout', '-B', $branch]);
    }

    public function add(string $repoPath, string $path): void
    {
        $this->run($repoPath, ['git', 'add', $path]);
    }

    public function diffForPaths(string $repoPath, array $paths): string
    {
        $args = ['git', 'diff', '--'];
        foreach ($paths as $path) {
            $args[] = $path;
        }

        return $this->runAllowFailure($repoPath, $args);
    }

    public function commit(string $repoPath, string $message): string
    {
        $this->run($repoPath, ['git', 'commit', '-m', $message]);

        return trim($this->run($repoPath, ['git', 'rev-parse', 'HEAD']));
    }

    public function pushBranch(string $repoPath, string $branch): void
    {
        $this->run($repoPath, ['git', 'push', '-u', 'origin', $branch]);
    }

    public function revertCommit(string $repoPath, string $sha): string
    {
        $this->run($repoPath, ['git', 'revert', '--no-edit', $sha]);

        return trim($this->run($repoPath, ['git', 'rev-parse', 'HEAD']));
    }

    public function originRepoFullName(string $repoPath): ?string
    {
        $remote = trim($this->run($repoPath, ['git', 'remote', 'get-url', 'origin']));
        if ($remote === '') {
            return null;
        }

        if (preg_match('#github\.com[:/](?<owner>[^/]+)/(?<repo>[^/.]+)(?:\.git)?$#i', $remote, $m)) {
            return $m['owner'].'/'.$m['repo'];
        }

        return null;
    }

    public function guardPathAllowed(string $relativePath): void
    {
        $normalized = str_replace('\\', '/', ltrim($relativePath, '/\\'));
        $allowed = (array) config('fixpulse_actions.guards.allowed_globs', []);
        $blocked = (array) config('fixpulse_actions.guards.blocked_globs', []);
        $blockedExtensions = (array) config('fixpulse_actions.guards.blocked_extensions', []);

        $isAllowed = collect($allowed)->contains(fn ($glob) => Str::is((string) $glob, $normalized));
        if (! $isAllowed) {
            throw new RuntimeException('Target path is outside allowed guardrails: '.$normalized);
        }

        $isBlocked = collect($blocked)->contains(fn ($glob) => Str::is((string) $glob, $normalized));
        if ($isBlocked) {
            throw new RuntimeException('Target path is blocked by guardrails: '.$normalized);
        }

        foreach ($blockedExtensions as $ext) {
            $trimmed = trim((string) $ext);
            if ($trimmed === '') {
                continue;
            }
            if (Str::endsWith(strtolower($normalized), strtolower($trimmed))) {
                throw new RuntimeException('Target extension is blocked by guardrails: '.$normalized);
            }
        }
    }

    public function run(string $cwd, array $command): string
    {
        $maxAttempts = 3;
        $lastError = 'Git command failed.';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $process = new Process($command, $cwd, null, null, 120);
            $process->run();

            if ($process->isSuccessful()) {
                return $process->getOutput();
            }

            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            $message = $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'Git command failed.');
            $lastError = $message;

            if (! $this->isTransientNetworkError($message) || $attempt === $maxAttempts) {
                throw new RuntimeException($message);
            }

            usleep(300000 * $attempt);
        }

        throw new RuntimeException($lastError);
    }

    public function runAllowFailure(string $cwd, array $command): string
    {
        $process = new Process($command, $cwd, null, null, 120);
        $process->run();

        return $process->getOutput().$process->getErrorOutput();
    }

    private function isTransientNetworkError(string $message): bool
    {
        $haystack = strtolower($message);

        return str_contains($haystack, 'getaddrinfo() thread failed to start')
            || str_contains($haystack, 'could not resolve host')
            || str_contains($haystack, 'failed to connect')
            || str_contains($haystack, 'connection reset')
            || str_contains($haystack, 'network is unreachable')
            || str_contains($haystack, 'operation timed out');
    }
}
