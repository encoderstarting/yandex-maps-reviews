<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class YandexMapsUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('Введите корректную ссылку на организацию в Яндекс Картах.');

            return;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $allowedHosts = ['yandex.ru', 'www.yandex.ru', 'yandex.com', 'www.yandex.com'];
        $isOrganizationPath = preg_match('#^/maps/org/(?:[^/]+/)?[0-9]+/?$#u', $path) === 1;

        if ($scheme !== 'https' || ! in_array($host, $allowedHosts, true) || ! $isOrganizationPath) {
            $fail('Разрешены только HTTPS-ссылки на организации в Яндекс Картах.');
        }
    }
}
