<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SsrfGuard
{
    private const BLOCKED_SUFFIXES = ['.local', '.internal', '.lan'];

    public function assertSafeUrl(string $url, int $maxRedirects = 3): void
    {
        $currentUrl = $url;

        for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
            $parts = $this->parseAndValidate($currentUrl);
            $this->validateHost($parts['host']);

            if ($redirects === $maxRedirects) {
                return;
            }

            try {
                $response = Http::timeout(10)
                    ->withoutRedirecting()
                    ->withHeaders(['User-Agent' => 'FixPulse-SSRF-Guard'])
                    ->head($currentUrl);
            } catch (\Throwable) {
                // Host failed at request-time; URL is still considered SSRF-safe
                // after host/IP checks because scan execution will handle availability.
                return;
            }

            if (! $response->redirect()) {
                return;
            }

            $location = $response->header('Location');
            if (! $location) {
                return;
            }

            $currentUrl = $this->resolveRedirect($currentUrl, $location);
        }
    }

    private function parseAndValidate(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new InvalidArgumentException('Invalid URL.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http/https URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URL userinfo is not allowed.');
        }

        $host = strtolower($parts['host'] ?? '');
        if ($host === '') {
            throw new InvalidArgumentException('URL host is required.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('Only ports 80 and 443 are allowed.');
        }

        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (Str::endsWith($host, $suffix)) {
                throw new InvalidArgumentException('Blocked hostname suffix.');
            }
        }

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port];
    }

    private function validateHost(string $host): void
    {
        foreach ($this->resolveHost($host) as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new InvalidArgumentException('Host resolves to private or reserved IP ranges.');
            }
        }
    }

    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            throw new InvalidArgumentException('Unable to resolve host.');
        }

        return array_values(array_unique($ips));
    }

    private function isBlockedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->isBlockedIpv6($ip);
        }

        return true;
    }

    private function isBlockedIpv6(string $ip): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return true;
        }

        $first = ord($bin[0]);
        $second = ord($bin[1]);

        if ($ip === '::1') {
            return true;
        }

        if ($first === 0xfc || $first === 0xfd) {
            return true;
        }

        if ($first === 0xfe && ($second & 0xc0) === 0x80) {
            return true;
        }

        return false;
    }

    private function resolveRedirect(string $currentUrl, string $location): string
    {
        if (Str::startsWith($location, ['http://', 'https://'])) {
            return $location;
        }

        $base = parse_url($currentUrl);
        if (! is_array($base) || empty($base['host']) || empty($base['scheme'])) {
            throw new InvalidArgumentException('Unable to resolve redirect URL.');
        }

        $port = isset($base['port']) ? ':'.$base['port'] : '';
        $path = Str::startsWith($location, '/') ? $location : '/'.$location;

        return sprintf('%s://%s%s%s', $base['scheme'], $base['host'], $port, $path);
    }
}
