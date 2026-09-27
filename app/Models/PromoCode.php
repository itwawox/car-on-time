<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Промокод: скидка на аренду (без доставки и доп. услуг) с ограничениями по сроку, классу и числу применений. */
#[Fillable(['code', 'discount_type', 'discount_value', 'min_days', 'max_uses', 'car_class_ids', 'promotion_id', 'starts_at', 'ends_at', 'is_active', 'note'])]
class PromoCode extends Model
{
    public const TYPES = ['percent' => 'Процент от аренды', 'fixed' => 'Фиксированная сумма, ₽'];

    protected static function booted(): void
    {
        static::saving(fn (PromoCode $promo) => $promo->code = self::normalize($promo->code));
    }

    protected function casts(): array
    {
        return [
            'car_class_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public static function normalize(?string $code): string
    {
        return mb_strtoupper(trim((string) $code));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = self::normalize($code);

        return $code === '' ? null : self::query()->where('code', $code)->first();
    }

    /** Сколько раз код уже использован: заявки с ним, кроме отказов. */
    public function usedCount(?int $exceptBookingId = null): int
    {
        return Booking::query()->where('promo_code', $this->code)->where('status', '!=', 'declined')
            ->when($exceptBookingId, fn ($q) => $q->whereKeyNot($exceptBookingId))->count();
    }

    /** Почему код не подходит к этой аренде; null — подходит. */
    public function rejectionReason(Car $car, int $days): ?string
    {
        return match (true) {
            ! $this->is_active => 'Промокод не действует.',
            $this->starts_at && $this->starts_at->isFuture() => 'Промокод начнёт действовать '.$this->starts_at->translatedFormat('j F').'.',
            $this->ends_at && $this->ends_at->isPast() => 'Срок действия промокода закончился.',
            $this->min_days && $days < $this->min_days => 'Промокод действует при аренде от '.$this->min_days.' суток.',
            $this->max_uses && $this->usedCount() >= $this->max_uses => 'Промокод уже использован.',
            ! empty($this->car_class_ids) && ! $car->classes()->whereKey($this->car_class_ids)->exists() => 'Промокод не действует для этой машины.',
            default => null,
        };
    }

    public function discountFor(int $rentTotal): int
    {
        $discount = $this->discount_type === 'fixed'
            ? (int) $this->discount_value
            : (int) round($rentTotal * min(100, (int) $this->discount_value) / 100);

        return max(0, min($discount, $rentTotal));
    }

    public function label(): string
    {
        return $this->discount_type === 'fixed'
            ? '−'.number_format((int) $this->discount_value, 0, ',', ' ').' ₽'
            : '−'.$this->discount_value.'%';
    }
}
