<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Contracts\OrganizationParser;
use App\Exceptions\YandexMaps\YandexMapsBlockedException;
use App\Exceptions\YandexMaps\YandexMapsSourceChangedException;
use App\Exceptions\YandexMaps\YandexMapsUnavailableException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMapsParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('yandex_maps.retry_delay_ms', 0);
        Http::preventStrayRequests();
    }

    public function test_parser_extracts_organization_and_all_review_pages(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/test/123456/' => Http::response($this->fixture('overview.html')),
            'https://yandex.ru/maps/org/123456/reviews/?page=1' => Http::response($this->fixture('reviews-page-1.html')),
            'https://yandex.ru/maps/org/123456/reviews/?page=2' => Http::response($this->fixture('reviews-page-2.html')),
        ]);

        $result = app(OrganizationParser::class)->parse(
            'https://yandex.ru/maps/org/test/123456/',
        );

        $this->assertSame('123456', $result->externalId);
        $this->assertSame('Тестовая кофейня', $result->name);
        $this->assertSame(4.8, $result->rating);
        $this->assertSame(128, $result->ratingsCount);
        $this->assertSame(3, $result->reviewsCount);
        $this->assertCount(3, $result->reviews);
        $this->assertSame('review-1', $result->reviews[0]->externalId);
        $this->assertSame('Анна', $result->reviews[0]->authorName);
        $this->assertSame(5, $result->reviews[0]->rating);
        $this->assertSame('Отличный кофе', $result->reviews[0]->text);
        $this->assertSame('Пользователь Яндекса', $result->reviews[2]->authorName);
        $this->assertNull($result->reviews[2]->text);

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://yandex.ru/maps/org/123456/reviews/?page=2');
    }

    public function test_parser_detects_changed_source_schema(): void
    {
        Http::fake([
            '*' => Http::response($this->fixture('source-changed.html')),
        ]);

        $this->expectException(YandexMapsSourceChangedException::class);
        $this->expectExceptionMessage('отсутствуют показатели');

        app(OrganizationParser::class)->parse('https://yandex.ru/maps/org/test/123456/');
    }

    public function test_parser_reports_captcha_as_blocking(): void
    {
        Http::fake([
            '*' => Http::response('<html><div class="smart-captcha"></div></html>'),
        ]);

        $this->expectException(YandexMapsBlockedException::class);

        app(OrganizationParser::class)->parse('https://yandex.ru/maps/org/test/123456/');
    }

    public function test_client_retries_temporary_server_error(): void
    {
        config()->set('yandex_maps.retries', 1);

        Http::fake([
            'https://yandex.ru/maps/org/test/123456/' => Http::sequence()
                ->pushStatus(503)
                ->push($this->fixture('overview.html')),
            'https://yandex.ru/maps/org/123456/reviews/?page=1' => Http::response($this->fixture('reviews-page-1.html')),
            'https://yandex.ru/maps/org/123456/reviews/?page=2' => Http::response($this->fixture('reviews-page-2.html')),
        ]);

        $result = app(OrganizationParser::class)->parse('https://yandex.ru/maps/org/test/123456/');

        $this->assertSame('123456', $result->externalId);
        Http::assertSentCount(4);
    }

    public function test_parser_stops_when_page_limit_is_exceeded(): void
    {
        config()->set('yandex_maps.max_pages', 1);

        Http::fake([
            'https://yandex.ru/maps/org/test/123456/' => Http::response($this->fixture('overview.html')),
            'https://yandex.ru/maps/org/123456/reviews/?page=1' => Http::response($this->fixture('reviews-page-1.html')),
        ]);

        $this->expectException(YandexMapsUnavailableException::class);
        $this->expectExceptionMessage('безопасный предел');

        app(OrganizationParser::class)->parse('https://yandex.ru/maps/org/test/123456/');
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(base_path("tests/Fixtures/YandexMaps/{$name}"));

        $this->assertIsString($contents);

        return $contents;
    }
}
