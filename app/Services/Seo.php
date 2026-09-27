<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Car;
use App\Models\Review;
use App\Models\Setting;
use App\Support\Seo\SeoSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Seo
{
    public function localBusiness(): array
    {
        $url = rtrim((string) config('app.url'), '/');

        $business = [
            '@context' => 'https://schema.org',
            '@type' => ['AutoRental', 'LocalBusiness'],
            '@id' => $url.'/#organization',
            'name' => Setting::get('brand_name', 'Car on Time'),
            'legalName' => Setting::get('legal_name'),
            'taxID' => Setting::get('inn'),
            'foundingDate' => Setting::get('founding_year'),
            'url' => $url,
            'telephone' => Setting::get('phone'),
            'email' => Setting::get('email'),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'postalCode' => Setting::get('postal_code'),
                'addressRegion' => Setting::get('address_region'),
                'addressLocality' => Setting::get('address_locality'),
                'streetAddress' => Setting::get('street_address') ?: Setting::get('address'),
                'addressCountry' => 'RU',
            ]),
            'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Республика Крым'],
            'currenciesAccepted' => 'RUB',
            'openingHours' => 'Mo-Su 00:00-23:59',
            'sameAs' => array_values(array_filter([Setting::get('vk')])),
        ];

        if ($logo = SeoSettings::general()['logo'] ?? null) {
            $business['logo'] = url(Storage::disk('public')->url($logo));
            $business['image'] = $business['logo'];
        }

        if ($pickup = Setting::get('pickup_point')) {
            $business['department'] = [
                '@type' => 'AutoRental',
                'name' => $pickup,
                'telephone' => Setting::get('phone'),
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Симферополь',
                    'addressRegion' => Setting::get('address_region'),
                    'streetAddress' => $pickup,
                    'addressCountry' => 'RU',
                ],
            ];
        }

        return array_filter($business, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Оценка компании по опубликованным отзывам. Без отзывов разметки нет — рейтинг не выдумываем.
     *
     * @param  iterable<Review>  $reviews
     * @param  array{count: int, avg: float}  $summary
     */
    public function reviews(iterable $reviews, array $summary): ?array
    {
        if ($summary['count'] < 1) {
            return null;
        }

        $url = rtrim((string) config('app.url'), '/');

        return [
            '@context' => 'https://schema.org',
            '@type' => ['AutoRental', 'LocalBusiness'],
            '@id' => $url.'/#organization',
            'name' => Setting::get('brand_name', 'Car on Time'),
            'url' => $url,
            'telephone' => Setting::get('phone'),
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => $summary['avg'],
                'reviewCount' => $summary['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ],
            'review' => collect($reviews)->take(10)->map(fn ($review) => [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $review->author],
                'datePublished' => $review->reviewed_at?->toDateString(),
                'reviewBody' => $review->body,
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $review->rating, 'bestRating' => 5],
            ])->values()->all(),
        ];
    }

    public function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => Setting::get('brand_name', 'Car on Time'),
            'url' => rtrim((string) config('app.url'), '/').'/',
            'inLanguage' => 'ru-RU',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function vehicle(Car $car): array
    {
        $url = rtrim((string) config('app.url'), '/');
        $from = $car->currentPriceFrom();
        $image = $car->coverUrl('', false);

        $offer = array_filter([
            '@type' => 'Offer',
            'url' => route('car.show', $car->slug),
            'priceCurrency' => 'RUB',
            'price' => $from,
            'availability' => 'https://schema.org/InStock',
            'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut',
            'priceSpecification' => $from ? [
                '@type' => 'UnitPriceSpecification',
                'price' => $from,
                'priceCurrency' => 'RUB',
                'unitCode' => 'DAY',
                'referenceQuantity' => ['@type' => 'QuantitativeValue', 'value' => 1, 'unitCode' => 'DAY'],
            ] : null,
            'seller' => ['@id' => $url.'/#organization'],
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Car',
            'name' => $car->name,
            'description' => $car->seo_description ?: Str::limit(trim(strip_tags((string) $car->description)), 300),
            'brand' => $car->brand ? ['@type' => 'Brand', 'name' => $car->brand->name] : null,
            'model' => $car->carModel?->name ?? $car->model,
            'vehicleTransmission' => $car->gearbox === 'mt' ? 'Механика' : 'Автомат',
            'seatingCapacity' => $car->seats,
            'fuelType' => $car->fuelLabel(),
            'driveWheelConfiguration' => [
                'fwd' => 'https://schema.org/FrontWheelDriveConfiguration',
                'rwd' => 'https://schema.org/RearWheelDriveConfiguration',
                '4wd' => 'https://schema.org/FourWheelDriveConfiguration',
            ][$car->drivetrain] ?? null,
            'bodyType' => $car->bodyType?->name,
            'vehicleModelDate' => $car->year_from ? (string) $car->year_from : null,
            'vehicleEngine' => $car->engine ? ['@type' => 'EngineSpecification', 'name' => $car->engine] : null,
            'image' => $image ? URL::to($image) : null,
            'offers' => $offer,
        ]);
    }

    public function article(Article $article): array
    {
        $url = rtrim((string) config('app.url'), '/');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'mainEntityOfPage' => $article->url(),
            'headline' => mb_substr($article->h1 ?: $article->title, 0, 110),
            'description' => $article->seo_description ?: $article->excerpt,
            'image' => $article->coverUrl('1200') ? url($article->coverUrl('1200')) : null,
            'datePublished' => ($article->published_at ?? $article->created_at)?->toAtomString(),
            'dateModified' => $article->updated_at?->toAtomString(),
            'articleSection' => $article->category,
            'wordCount' => str_word_count(strip_tags((string) $article->content), 0, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя'),
            'inLanguage' => 'ru-RU',
            'author' => $article->author_name
                ? array_filter(['@type' => 'Person', 'name' => $article->author_name, 'jobTitle' => $article->author_role])
                : ['@type' => 'Organization', 'name' => Setting::get('brand_name', 'Car on Time'), 'url' => $url],
            'publisher' => ['@id' => $url.'/#organization'],
        ]);
    }

    /**
     * Список машин на странице каталога — помогает поисковикам понять листинг.
     *
     * @param  iterable<Car>  $cars
     */
    public function itemList(iterable $cars, string $name): ?array
    {
        $items = collect($cars)->values()->map(fn (Car $car, int $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => route('car.show', $car->slug),
            'name' => $car->name,
        ])->all();

        return $items ? [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ] : null;
    }

    public function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'] ?? null,
            ])->all(),
        ];
    }

    public function faq(iterable $faqs): ?array
    {
        $faqs = collect($faqs);
        if ($faqs->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq->answer,
                ],
            ])->values()->all(),
        ];
    }
}
