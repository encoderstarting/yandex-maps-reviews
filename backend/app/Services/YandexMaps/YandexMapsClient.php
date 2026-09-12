<?php

namespace App\Services\YandexMaps;

use App\Exceptions\YandexMaps\YandexMapsBlockedException;
use App\Exceptions\YandexMaps\YandexMapsUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

class YandexMapsClient
{
    public function __construct(private readonly Factory $http) {}

    public function fetch(string $url): string
    {
        $attempts = (int) config('yandex_maps.retries', 2) + 1;
        $delayMilliseconds = (int) config('yandex_maps.retry_delay_ms', 500);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->send($url);
            } catch (ConnectionException $exception) {
                if ($attempt === $attempts) {
                    throw new YandexMapsUnavailableException(
                        'Яндекс Карты не ответили за отведённое время.',
                        previous: $exception,
                    );
                }

                $this->waitBeforeRetry($delayMilliseconds, $attempt);

                continue;
            }

            if (in_array($response->status(), [403, 429], true) || $this->containsCaptcha($response->body())) {
                throw new YandexMapsBlockedException('Яндекс Карты ограничили автоматический доступ.');
            }

            if ($response->successful()) {
                return $response->body();
            }

            if ($attempt < $attempts && $response->serverError()) {
                $this->waitBeforeRetry($delayMilliseconds, $attempt);

                continue;
            }

            throw new YandexMapsUnavailableException(
                "Яндекс Карты вернули HTTP {$response->status()}.",
            );
        }

        throw new YandexMapsUnavailableException('Не удалось получить ответ Яндекс Карт.');
    }

    private function send(string $url): Response
    {
        return $this->http
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'User-Agent' => (string) config('yandex_maps.user_agent'),
            ])
            ->connectTimeout((int) config('yandex_maps.connect_timeout', 5))
            ->timeout((int) config('yandex_maps.timeout', 20))
            ->get($url);
    }

    private function containsCaptcha(string $body): bool
    {
        $body = mb_strtolower($body);

        return str_contains($body, 'smart-captcha')
            || str_contains($body, 'showcaptcha')
            || str_contains($body, 'подтвердите, что запросы отправляли вы');
    }

    private function waitBeforeRetry(int $delayMilliseconds, int $attempt): void
    {
        if ($delayMilliseconds > 0) {
            usleep($delayMilliseconds * $attempt * 1000);
        }
    }
}
