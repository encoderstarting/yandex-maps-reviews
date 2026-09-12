<?php

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationParser;
use App\Data\ParsedOrganization;
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
        $reviewsById = [];
        $totalPages = 1;

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

            $totalPages = $reviewPage->totalPages;

            if ($totalPages > $maxPages) {
                throw new YandexMapsUnavailableException(
                    "Количество страниц отзывов превышает безопасный предел {$maxPages}.",
                );
            }

            $newReviews = 0;

            foreach ($reviewPage->reviews as $review) {
                if (! isset($reviewsById[$review->externalId])) {
                    $newReviews++;
                }

                $reviewsById[$review->externalId] = $review;
            }

            $onProgress?->__invoke($page, $totalPages, count($reviewsById));

            if ($page < $totalPages && ($reviewPage->reviews === [] || $newReviews === 0)) {
                throw new YandexMapsSourceChangedException(
                    'Пагинация остановилась до получения всех отзывов.',
                );
            }
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
