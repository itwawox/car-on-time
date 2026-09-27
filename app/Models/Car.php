<?php

namespace App\Models;

use App\Support\CarFacts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'partner_id', 'brand_id', 'car_model_id', 'body_type_id', 'name', 'slug', 'model',
    'year_from', 'year_to', 'gearbox', 'fuel', 'engine', 'seats', 'consumption',
    'power_hp', 'engine_l', 'consumption_mixed', 'trunk_l', 'clearance_mm',
    'drivetrain', 'specs_verified', 'deposit', 'min_age', 'min_experience', 'min_days', 'daily_km',
    'description', 'description_generated_at', 'seo_text', 'seo_title', 'seo_description', 'status',
    'legacy_image', 'sort', 'search_aliases',
])]
class Car extends Model implements HasMedia
{
    use InteractsWithMedia;
    use Searchable;

    protected function casts(): array
    {
        return [
            'specs_verified' => 'boolean',
            'description_generated_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Без очереди: на сервере нет воркера, превью должны появиться сразу после загрузки
        $this->addMediaConversion('card')
            ->fit(Fit::Max, 640, 400)
            ->format('webp')
            ->quality(80)
            ->nonQueued()
            ->performOnCollections('gallery');

        $this->addMediaConversion('large')
            ->fit(Fit::Max, 1280, 800)
            ->format('webp')
            ->quality(80)
            ->nonQueued()
            ->performOnCollections('gallery');
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<CarModel, $this> */
    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    /** @return BelongsTo<BodyType, $this> */
    public function bodyType(): BelongsTo
    {
        return $this->belongsTo(BodyType::class);
    }

    /** @return BelongsToMany<CarClass, $this> */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(CarClass::class);
    }

    /** @return BelongsToMany<Feature, $this> */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    /** @return HasMany<CarPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(CarPrice::class);
    }

    /** @return HasMany<CarBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(CarBlock::class);
    }

    /**
     * Название для витрины: без пометок коробки «(M/T)», «(А/Т)» — коробка и так показана чипом;
     * пробел перед скобкой, «NEW» → «new». Исходное name остаётся для поиска и админки.
     */
    public function displayName(): string
    {
        $n = (string) $this->name;
        $n = preg_replace('/\s*\((?:[MМ]|[AА])\s*\/\s*[TТ]\)/u', '', $n);
        $n = preg_replace('/\s*\((?:МКПП|АКПП|механика|автомат)\)/iu', '', $n);
        $n = preg_replace('/(\S)\(/u', '$1 (', $n);
        $n = preg_replace('/\bNEW\b/u', 'new', $n);
        $n = preg_replace('/\bCROSS\b/u', 'Cross', $n);

        return trim(preg_replace('/\s{2,}/u', ' ', $n));
    }

    /** Для заголовков страниц: «Renault Logan на механике» — различает машины, отличающиеся только коробкой. */
    public function seoName(): string
    {
        return $this->displayName().($this->gearbox === 'mt' && $this->fuel !== 'electric' ? ' на механике' : '');
    }

    private ?CarFacts $factsCache = null;

    /** Мощность, расход, багажник и т. п. — точные значения машины или типичные для модели. */
    public function facts(): CarFacts
    {
        return $this->factsCache ??= new CarFacts($this);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function currentPriceFrom(): ?int
    {
        if ($this->relationLoaded('prices')) {
            return $this->prices->min('price');
        }

        return $this->prices()->orderBy('price')->value('price');
    }

    public function coverUrl(string $conversion = '', bool $webp = true): ?string
    {
        $media = $this->getFirstMedia('gallery');
        if ($media) {
            // $webp = false — оригинал jpg: для превью в мессенджерах и Яндекс.Картинок
            return $webp && $conversion !== '' && $media->hasGeneratedConversion($conversion)
                ? $media->getUrl($conversion)
                : $media->getUrl();
        }

        if ($this->legacy_image) {
            // В импортированных путях встречаются HTML-сущности (&#32; вместо пробела)
            $path = html_entity_decode($this->legacy_image, ENT_QUOTES | ENT_HTML5);

            // webp-копия (php artisan images:webp) — на 40–80% легче jpg/png
            $webpPath = (string) preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
            if ($webp && $webpPath !== $path && is_file(public_path($webpPath))) {
                $path = $webpPath;
            }

            return asset(implode('/', array_map('rawurlencode', explode('/', $path))));
        }

        return null;
    }

    /**
     * Все фото для галереи на странице машины.
     *
     * @return list<array{src: string, srcset: ?string, thumb: string, full: string, alt: string}>
     */
    public function galleryImages(): array
    {
        $images = $this->getMedia('gallery')->map(function ($media) {
            $url = fn (string $conversion) => $media->hasGeneratedConversion($conversion) ? $media->getUrl($conversion) : $media->getUrl();

            return [
                'src' => $url('large'),
                'srcset' => collect(['card' => 640, 'large' => 1280])
                    ->filter(fn ($w, $conversion) => $media->hasGeneratedConversion($conversion))
                    ->map(fn ($w, $conversion) => $media->getUrl($conversion).' '.$w.'w')
                    ->implode(', ') ?: null,
                'thumb' => $url('card'),
                'full' => $media->getUrl(),
                'alt' => (string) ($media->getCustomProperty('alt') ?: 'Аренда '.$this->name.' в Крыму'),
            ];
        })->values()->all();

        if (! $images && ($cover = $this->coverUrl('large'))) {
            $images[] = ['src' => $cover, 'srcset' => null, 'thumb' => $cover, 'full' => $this->coverUrl('', false) ?? $cover, 'alt' => $this->coverAlt()];
        }

        return $images;
    }

    /** «…card.webp 640w, …large.webp 1280w» — браузер сам выберет размер под экран. */
    public function coverSrcset(): ?string
    {
        $media = $this->getFirstMedia('gallery');
        if (! $media) {
            return null;
        }

        return collect(['card' => 640, 'large' => 1280])
            ->filter(fn ($w, $conversion) => $media->hasGeneratedConversion($conversion))
            ->map(fn ($w, $conversion) => $media->getUrl($conversion).' '.$w.'w')
            ->implode(', ') ?: null;
    }

    public function coverAlt(): string
    {
        return (string) ($this->getFirstMedia('gallery')?->getCustomProperty('alt') ?: 'Аренда '.$this->name.' в Крыму');
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published';
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['brand', 'media', 'classes', 'bodyType', 'features', 'prices']);
    }

    /**
     * Документ для поиска. Текстовые поля взвешивает движок,
     * остальные служат фильтрами (коробка, цена, места).
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['brand', 'classes', 'bodyType', 'features', 'prices']);

        $split = fn (?string $text) => array_filter(array_map('trim', preg_split('/[,;\n]+/u', (string) $text) ?: []));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand?->name,
            'brand_aliases' => $split($this->brand?->search_aliases),
            'model' => $this->model,
            'aliases' => $split($this->search_aliases),
            'categories' => [
                ...$this->classes->pluck('name')->all(),
                $this->bodyType?->name,
                ...$this->classes->flatMap(fn ($class) => $split($class->search_aliases))->all(),
                ...$split($this->bodyType?->search_aliases),
            ],
            'features' => $this->features->pluck('name')->all(),
            'brand_id' => $this->brand_id,
            'class_slugs' => $this->classes->pluck('slug')->all(),
            'body_slug' => $this->bodyType?->slug,
            'gearbox' => $this->gearbox,
            'seats' => $this->seats,
            'fuel' => $this->fuel,
            'drivetrain' => $this->drivetrain,
            'price_from' => $this->currentPriceFrom(),
            'sort' => $this->sort,
        ];
    }

    public function gearboxLabel(): string
    {
        return $this->gearbox === 'mt' ? 'Механика' : 'Автомат';
    }

    public const DRIVETRAINS = ['fwd' => 'Передний', 'rwd' => 'Задний', '4wd' => 'Полный'];

    public const FUELS = ['petrol' => 'Бензин', 'diesel' => 'Дизель', 'electric' => 'Электро', 'hybrid' => 'Гибрид', 'gas' => 'Газ'];

    public function drivetrainLabel(): ?string
    {
        return self::DRIVETRAINS[$this->drivetrain] ?? null;
    }

    public function fuelLabel(): ?string
    {
        return self::FUELS[$this->fuel] ?? $this->fuel;
    }

    /** «2017–2020», «с 2023», null. */
    public function yearsLabel(): ?string
    {
        if ($this->year_from && $this->year_to && $this->year_to !== $this->year_from) {
            return $this->year_from.'–'.$this->year_to;
        }

        return $this->year_from ? (string) $this->year_from : null;
    }
}
