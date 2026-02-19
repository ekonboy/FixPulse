<?php

$csv = static function (string $value): array {
    return array_values(array_filter(array_map('trim', explode(',', $value))));
};

return [
    'git' => [
        'repo_path' => env('FIXPULSE_REPO_PATH', dirname(base_path(), 2)),
        'default_branch' => env('FIXPULSE_REPO_DEFAULT_BRANCH', 'main'),
        'branch_prefix' => env('FIXPULSE_BRANCH_PREFIX', 'fixpulse'),
        'max_issue_key_length' => (int) env('FIXPULSE_MAX_ISSUE_KEY_LENGTH', 60),
    ],

    'guards' => [
        'allowed_globs' => $csv((string) env(
            'FIXPULSE_ALLOWED_GLOBS',
            'resources/**,public/**,assets/**,src/**,vite.config.*,package.json,deploy/nginx/**'
        )),
        'blocked_globs' => $csv((string) env(
            'FIXPULSE_BLOCKED_GLOBS',
            '.env,.env.*,config/**,database/**,app/**,storage/**'
        )),
        'blocked_extensions' => $csv((string) env(
            'FIXPULSE_BLOCKED_EXTENSIONS',
            '.env,.key,.pem,.p12'
        )),
        'max_files_changed' => (int) env('FIXPULSE_MAX_FILES_CHANGED', 12),
        'max_total_diff_bytes' => (int) env('FIXPULSE_MAX_TOTAL_DIFF_BYTES', 262144),
    ],

    'autofix' => [
        'enabled' => filter_var(env('FIXPULSE_AUTOFIX_ENABLED', true), FILTER_VALIDATE_BOOL),
        'issue_keys' => $csv((string) env(
            'FIXPULSE_AUTOFIX_ISSUE_KEYS',
            'offscreen-images,render-blocking-resources,uses-text-compression,font-display'
        )),
        'max_files_per_action' => (int) env('FIXPULSE_AUTOFIX_MAX_FILES', 8),
    ],
];
