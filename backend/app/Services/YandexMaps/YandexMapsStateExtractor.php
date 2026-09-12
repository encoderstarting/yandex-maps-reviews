<?php

namespace App\Services\YandexMaps;

use App\Data\ParsedBusiness;
use App\Data\ParsedReview;
use App\Data\ParsedReviewPage;
use App\Exceptions\YandexMaps\YandexMapsSourceChangedException;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Exception;
use JsonException;

class YandexMapsStateExtractor
{
    public function extractBusiness(string $html): ParsedBusiness
    {
        $business = $this->businessData($this->decodeState($html));
        $ratingData = $business['ratingData'] ?? null;

        if (! is_array($ratingData)) {
            throw new YandexMapsSourceChangedException('В ответе отсутствуют показатели организации.');
        }

        $externalId = $business['id'] ?? null;
        $name = $business['title'] ?? null;
        $rating = $ratingData['ratingValue'] ?? null;
        $ratingsCount = $ratingData['ratingCount'] ?? null;
        $reviewsCount = $ratingData['reviewCount'] ?? null;

        if (
            ! is_scalar($externalId)
            || ! is_string($name)
            || $name === ''
            || ! is_numeric($rating)
            || ! is_numeric($ratingsCount)
            || ! is_numeric($reviewsCount)
        ) {
            throw new YandexMapsSourceChangedException('Формат данных организации изменился.');
        }

        $rating = (float) $rating;
        $ratingsCount = (int) $ratingsCount;
        $reviewsCount = (int) $reviewsCount;

        if ($rating < 0 || $rating > 5 || $ratingsCount < 0 || $reviewsCount < 0) {
            throw new YandexMapsSourceChangedException('Яндекс Карты вернули некорректные показатели организации.');
        }

        return new ParsedBusiness(
            externalId: (string) $externalId,
            name: $name,
            rating: round($rating, 1),
            ratingsCount: $ratingsCount,
            reviewsCount: $reviewsCount,
        );
    }

    public function extractReviewPage(string $html, string $expectedBusinessId): ParsedReviewPage
    {
        $business = $this->businessData($this->decodeState($html));
        $reviewResults = $business['reviewResults'] ?? null;

        if (! is_array($reviewResults) || ! is_array($reviewResults['reviews'] ?? null)) {
            throw new YandexMapsSourceChangedException('В ответе отсутствует список отзывов.');
        }

        $params = $reviewResults['params'] ?? null;

        if (! is_array($params)) {
            throw new YandexMapsSourceChangedException('В ответе отсутствуют параметры страниц отзывов.');
        }

        $currentPage = $params['page'] ?? null;
        $totalPages = $params['totalPages'] ?? null;
        $totalReviews = $params['count'] ?? null;

        if (! is_numeric($currentPage) || ! is_numeric($totalPages) || ! is_numeric($totalReviews)) {
            throw new YandexMapsSourceChangedException('Формат пагинации отзывов изменился.');
        }

        $reviews = [];

        foreach ($reviewResults['reviews'] as $review) {
            if (! is_array($review)) {
                throw new YandexMapsSourceChangedException('Формат отзыва изменился.');
            }

            $reviews[] = $this->extractReview($review, $expectedBusinessId);
        }

        return new ParsedReviewPage(
            reviews: $reviews,
            currentPage: (int) $currentPage,
            totalPages: (int) $totalPages,
            totalReviews: (int) $totalReviews,
        );
    }

    /** @return array<string, mixed> */
    private function decodeState(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new YandexMapsSourceChangedException('Не удалось прочитать HTML Яндекс Карт.');
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(
            '//script[contains(concat(" ", normalize-space(@class), " "), " state-view ")]',
        );
        $json = $nodes?->item(0)?->textContent;

        if (! is_string($json) || trim($json) === '') {
            throw new YandexMapsSourceChangedException('В HTML отсутствует JSON состояния Яндекс Карт.');
        }

        try {
            $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new YandexMapsSourceChangedException(
                'JSON состояния Яндекс Карт повреждён.',
                previous: $exception,
            );
        }

        if (! is_array($state)) {
            throw new YandexMapsSourceChangedException('JSON состояния Яндекс Карт имеет неизвестный формат.');
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function businessData(array $state): array
    {
        $business = data_get($state, 'stack.0.results.items.0');

        if (! is_array($business)) {
            throw new YandexMapsSourceChangedException('В ответе не найдена карточка организации.');
        }

        return $business;
    }

    /** @param array<string, mixed> $review */
    private function extractReview(array $review, string $expectedBusinessId): ParsedReview
    {
        $externalId = $review['reviewId'] ?? null;
        $businessId = $review['businessId'] ?? null;
        $rating = $review['rating'] ?? null;
        $updatedTime = $review['updatedTime'] ?? null;
        $authorName = data_get($review, 'author.name');
        $text = $review['text'] ?? null;

        if (
            ! is_string($externalId)
            || $externalId === ''
            || (string) $businessId !== $expectedBusinessId
            || ! is_numeric($rating)
            || ! is_string($updatedTime)
        ) {
            throw new YandexMapsSourceChangedException('Формат отзыва изменился.');
        }

        $rating = (int) $rating;

        if ($rating < 1 || $rating > 5 || ($text !== null && ! is_string($text))) {
            throw new YandexMapsSourceChangedException('Отзыв содержит некорректные данные.');
        }

        try {
            $publishedAt = new DateTimeImmutable($updatedTime);
        } catch (Exception $exception) {
            throw new YandexMapsSourceChangedException(
                'Дата отзыва имеет неизвестный формат.',
                previous: $exception,
            );
        }

        return new ParsedReview(
            externalId: $externalId,
            authorName: is_string($authorName) && $authorName !== '' ? $authorName : 'Пользователь Яндекса',
            rating: $rating,
            text: is_string($text) && $text !== '' ? $text : null,
            publishedAt: $publishedAt,
        );
    }
}
