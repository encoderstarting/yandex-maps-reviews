<?php

namespace App\Support;

class YandexMapsUrlNormalizer
{
    public function normalize(string $url): string
    {
        $parts = parse_url(trim($url));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = preg_replace('#/+#', '/', (string) ($parts['path'] ?? '')) ?? '';
        $path = rtrim($path, '/');

        return "https://{$host}{$path}";
    }
}
