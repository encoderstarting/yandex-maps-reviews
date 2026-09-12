<?php

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationParser;
use App\Data\ParsedOrganization;
use App\Exceptions\YandexMaps\YandexMapsLimitExceededException;
use App\Exceptions\YandexMaps\YandexMapsSourceChangedException;
use App\Exceptions\YandexMaps\YandexMapsUnavailableException;
use Closure;

class YandexMapsParser implements OrganizationParser
{
    public function __construct(
        private readonly YandexMapsClient $client,
        private readonly YandexMapsStateExtractor $extractor,
    ) {}

    public function parse(string $url, ?Closure $onProgress = null): ParsedOrganization
    {
        $business = $this->extractor->extractBusiness($this->client->fetch($url));
        $reviewBaseUrl = $this->reviewBaseUrl($url, $business->externalId);
        $maxPages = (int) config('yandex_maps.max_pages', 100);
        $maxReviews = (int) config('yandex_maps.max_reviews', 600);
        $reviewsPerPage = (int) config('yandex_maps.reviews_per_page', 50);
        $reviewsById = [];
        $totalPages = 1;
        $reportedTotalPages = 1;
        $lastProcessedPage = 0;

        if ($maxPages < 1 || $maxReviews < 1 || $reviewsPerPage < 1) {
            throw new YandexMapsLimitExceededException('Лимиты парсера настроены некорректно.');
        }

        for ($page = 1; $page <= $totalPages; $page++) {
            $html = $this->client->fetch($this->pageUrl($reviewBaseUrl, $page));
            $pageBusiness = $this->extractor->extractBusiness($html);

            if ($pageBusiness->externalId !== $business->externalId) {
                throw new YandexMapsSourceChangedException('Яндекс Карты вернули другую организацию.');
            }

            if ($page === 1) {
                $business = $pageBusiness;
            }

            $reviewPage = $this->extractor->extractReviewPage($html, $business->externalId);

            if ($reviewPage->currentPage !== $page || $reviewPage->totalPages < $page) {
                throw new YandexMapsSourceChangedException('Яндекс Карты вернули неожиданную страницу отзывов.');
            }

            $reportedTotalPages = $reviewPage->totalPages;
            $totalPages = min($reportedTotalPages, $maxPages);
            $lastProcessedPage = $page;

            $newReviews = 0;

            foreach ($reviewPage->reviews as $review) {
                if (count($reviewsById) >= $maxReviews) {
                    break;
                }

                if (! isset($reviewsById[$review->externalId])) {
                    $newReviews++;
                }

                $reviewsById[$review->externalId] = $review;
            }

            $progressPages = min($totalPages, (int) ceil($maxReviews / $reviewsPerPage));
            $onProgress?->__invoke(min($page, $progressPages), $progressPages, count($reviewsById));

            if (count($reviewsById) >= $maxReviews) {
                break;
            }

            if ($page < $totalPages && ($reviewPage->reviews === [] || $newReviews === 0)) {
                throw new YandexMapsSourceChangedException(
                    'Пагинация остановилась до получения всех отзывов.',
                );
            }
        }

        if ($lastProcessedPage === $maxPages
            && $reportedTotalPages > $maxPages
            && count($reviewsById) < $maxReviews) {
            throw new YandexMapsLimitExceededException(
                "Парсер достиг технического лимита страниц ({$maxPages}) до получения {$maxReviews} уникальных отзывов.",
            );
        }

        return new ParsedOrganization(
            externalId: $business->externalId,
            name: $business->name,
            rating: $business->rating,
            ratingsCount: $business->ratingsCount,
            reviewsCount: $business->reviewsCount,
            reviews: array_values($reviewsById),
        );
    }

    private function reviewBaseUrl(string $sourceUrl, string $businessId): string
    {
        $host = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $allowedHosts = (array) config('yandex_maps.allowed_hosts', []);

        if (! in_array($host, $allowedHosts, true)) {
            throw new YandexMapsUnavailableException('Домен ссылки не поддерживается парсером.');
        }

        return "https://{$host}/maps/org/{$businessId}/reviews/";
    }

    private function pageUrl(string $baseUrl, int $page): string
    {
        return $baseUrl.'?'.http_build_query(['page' => $page]);
    }
}
