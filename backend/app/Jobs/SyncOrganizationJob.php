<?php

namespace App\Jobs;

use App\Actions\PersistParsedOrganization;
use App\Contracts\OrganizationParser;
use App\Exceptions\YandexMaps\YandexMapsBlockedException;
use App\Exceptions\YandexMaps\YandexMapsException;
use App\Exceptions\YandexMaps\YandexMapsSourceChangedException;
use App\Exceptions\YandexMaps\YandexMapsUnavailableException;
use App\Models\Organization;
use App\Models\SyncRun;
use App\SyncStatus;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SyncOrganizationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 1800;

    public int $uniqueFor = 10800;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $syncRunId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->organizationId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("organization:{$this->organizationId}"))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        OrganizationParser $parser,
        PersistParsedOrganization $persister,
    ): void {
        $organization = Organization::query()->findOrFail($this->organizationId);
        $syncRun = SyncRun::query()
            ->whereKey($this->syncRunId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        if ($syncRun->status === SyncStatus::Completed) {
            return;
        }

        $syncRun->update([
            'status' => SyncStatus::Running,
            'progress' => 0,
            'processed_reviews' => 0,
            'error_code' => null,
            'error_message' => null,
            'started_at' => $syncRun->started_at ?? now(),
            'finished_at' => null,
        ]);

        try {
            $parsed = $parser->parse(
                $organization->source_url,
                function (int $currentPage, int $totalPages, int $processedReviews) use ($syncRun): void {
                    $syncRun->update([
                        'progress' => min(95, (int) floor($currentPage / max(1, $totalPages) * 95)),
                        'processed_reviews' => $processedReviews,
                    ]);
                },
            );

            $persister->execute($organization, $syncRun, $parsed);
        } catch (YandexMapsBlockedException $exception) {
            $this->finishWithError($syncRun, SyncStatus::Blocked, 'YANDEX_BLOCKED', $exception->getMessage());
        } catch (YandexMapsSourceChangedException $exception) {
            $this->finishWithError($syncRun, SyncStatus::SourceChanged, 'SOURCE_CHANGED', $exception->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $syncRun = SyncRun::query()->find($this->syncRunId);

        if ($syncRun === null || $syncRun->status === SyncStatus::Completed) {
            return;
        }

        $errorCode = $exception instanceof YandexMapsUnavailableException
            ? 'YANDEX_UNAVAILABLE'
            : 'SYNC_FAILED';
        $message = $exception instanceof YandexMapsException
            ? $exception->getMessage()
            : 'Синхронизация завершилась внутренней ошибкой.';

        $this->finishWithError($syncRun, SyncStatus::Failed, $errorCode, $message);
    }

    private function finishWithError(
        SyncRun $syncRun,
        SyncStatus $status,
        string $errorCode,
        string $message,
    ): void {
        $syncRun->update([
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
