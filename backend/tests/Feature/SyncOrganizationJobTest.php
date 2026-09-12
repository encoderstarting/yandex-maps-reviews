<?php

namespace Tests\Feature;

use App\Actions\PersistParsedOrganization;
use App\Contracts\OrganizationParser;
use App\Data\ParsedOrganization;
use App\Data\ParsedReview;
use App\Exceptions\YandexMaps\YandexMapsBlockedException;
use App\Exceptions\YandexMaps\YandexMapsLimitExceededException;
use App\Exceptions\YandexMaps\YandexMapsSourceChangedException;
use App\Exceptions\YandexMaps\YandexMapsUnavailableException;
use App\Jobs\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\SyncRun;
use App\SyncStatus;
use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SyncOrganizationJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_job_saves_result_reviews_and_progress(): void
    {
        $organization = Organization::factory()->create([
            'external_id' => null,
            'name' => null,
            'rating' => null,
            'ratings_count' => null,
            'reviews_count' => null,
            'last_synced_at' => null,
        ]);
        $syncRun = SyncRun::factory()->for($organization)->create();
        $parser = $this->parser(function (string $url, ?Closure $onProgress): ParsedOrganization {
            $this->assertStringContainsString('yandex.ru/maps/', $url);
            $onProgress?->__invoke(1, 2, 1);
            $onProgress?->__invoke(2, 2, 2);

            return $this->parsedOrganization();
        });

        (new SyncOrganizationJob($organization->id, $syncRun->id))->handle(
            $parser,
            app(PersistParsedOrganization::class),
        );

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'external_id' => '123456',
            'name' => 'Тестовая кофейня',
            'ratings_count' => 128,
            'reviews_count' => 2,
        ]);
        $this->assertDatabaseHas('sync_runs', [
            'id' => $syncRun->id,
            'status' => SyncStatus::Completed->value,
            'progress' => 100,
            'processed_reviews' => 2,
        ]);
        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'author_name' => 'Анна',
            'rating' => 5,
        ]);
        $this->assertDatabaseCount('organization_snapshots', 1);
    }

    public function test_repeated_import_updates_reviews_and_stores_only_changed_snapshot(): void
    {
        $organization = Organization::factory()->create();
        $firstResult = $this->parsedOrganization();

        for ($runNumber = 0; $runNumber < 2; $runNumber++) {
            $syncRun = SyncRun::factory()->for($organization)->create();
            (new SyncOrganizationJob($organization->id, $syncRun->id))->handle(
                $this->parser(fn (): ParsedOrganization => $firstResult),
                app(PersistParsedOrganization::class),
            );
        }

        $thirdRun = SyncRun::factory()->for($organization)->create();
        $changedResult = new ParsedOrganization(
            externalId: '123456',
            name: 'Тестовая кофейня',
            rating: 4.9,
            ratingsCount: 130,
            reviewsCount: 2,
            reviews: [
                new ParsedReview('review-1', 'Анна', 4, 'Оценка изменена', new DateTimeImmutable('2026-09-01T12:00:00+03:00')),
                new ParsedReview('review-2', 'Иван', 4, null, new DateTimeImmutable('2026-09-02T12:00:00+03:00')),
            ],
        );
        (new SyncOrganizationJob($organization->id, $thirdRun->id))->handle(
            $this->parser(fn (): ParsedOrganization => $changedResult),
            app(PersistParsedOrganization::class),
        );

        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'rating' => 4,
            'text' => 'Оценка изменена',
        ]);
        $this->assertDatabaseCount('organization_snapshots', 2);
    }

    public function test_job_records_blocking_and_source_change_as_terminal_statuses(): void
    {
        $organization = Organization::factory()->create();
        $blockedRun = SyncRun::factory()->for($organization)->create();
        $changedRun = SyncRun::factory()->for($organization)->create();

        (new SyncOrganizationJob($organization->id, $blockedRun->id))->handle(
            $this->parser(fn () => throw new YandexMapsBlockedException('Источник запросил CAPTCHA.')),
            app(PersistParsedOrganization::class),
        );
        (new SyncOrganizationJob($organization->id, $changedRun->id))->handle(
            $this->parser(fn () => throw new YandexMapsSourceChangedException('Схема источника изменилась.')),
            app(PersistParsedOrganization::class),
        );

        $this->assertDatabaseHas('sync_runs', [
            'id' => $blockedRun->id,
            'status' => SyncStatus::Blocked->value,
            'error_code' => 'YANDEX_BLOCKED',
        ]);
        $this->assertDatabaseHas('sync_runs', [
            'id' => $changedRun->id,
            'status' => SyncStatus::SourceChanged->value,
            'error_code' => 'SOURCE_CHANGED',
        ]);
    }

    public function test_final_temporary_failure_is_recorded_after_queue_retries(): void
    {
        $organization = Organization::factory()->create();
        $syncRun = SyncRun::factory()->for($organization)->create();
        $job = new SyncOrganizationJob($organization->id, $syncRun->id);
        $exception = new YandexMapsUnavailableException('Яндекс Карты временно недоступны.');

        try {
            $job->handle(
                $this->parser(fn () => throw $exception),
                app(PersistParsedOrganization::class),
            );
            $this->fail('Временная ошибка должна быть передана очереди для повтора.');
        } catch (YandexMapsUnavailableException $caught) {
            $this->assertSame($exception, $caught);
            $job->failed($caught);
        }

        $this->assertDatabaseHas('sync_runs', [
            'id' => $syncRun->id,
            'status' => SyncStatus::Failed->value,
            'error_code' => 'YANDEX_UNAVAILABLE',
        ]);
    }

    public function test_page_limit_is_recorded_without_queue_retry(): void
    {
        $organization = Organization::factory()->create();
        $syncRun = SyncRun::factory()->for($organization)->create();

        (new SyncOrganizationJob($organization->id, $syncRun->id))->handle(
            $this->parser(fn () => throw new YandexMapsLimitExceededException(
                'Достигнут технический лимит.',
            )),
            app(PersistParsedOrganization::class),
        );

        $this->assertDatabaseHas('sync_runs', [
            'id' => $syncRun->id,
            'status' => SyncStatus::Failed->value,
            'error_code' => 'SYNC_LIMIT_REACHED',
            'error_message' => 'Достигнут технический лимит.',
        ]);
    }

    private function parsedOrganization(): ParsedOrganization
    {
        return new ParsedOrganization(
            externalId: '123456',
            name: 'Тестовая кофейня',
            rating: 4.8,
            ratingsCount: 128,
            reviewsCount: 2,
            reviews: [
                new ParsedReview('review-1', 'Анна', 5, 'Отличный кофе', new DateTimeImmutable('2026-09-01T12:00:00+03:00')),
                new ParsedReview('review-2', 'Иван', 4, null, new DateTimeImmutable('2026-09-02T12:00:00+03:00')),
            ],
        );
    }

    private function parser(Closure $callback): OrganizationParser
    {
        return new class($callback) implements OrganizationParser
        {
            public function __construct(private readonly Closure $callback) {}

            public function parse(string $url, ?Closure $onProgress = null): ParsedOrganization
            {
                return ($this->callback)($url, $onProgress);
            }
        };
    }
}
