<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarPrice;
use App\Models\Location;
use App\Models\PromoCode;
use App\Models\Season;
use App\Support\Availability;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class QuoteCalculator
{
    public function quote(
        Car $car,
        CarbonInterface $start,
        CarbonInterface $end,
        ?Location $pickup = null,
        ?Location $return = null,
        array $extras = [],
        ?string $promoCode = null,
    ): array {
        if ($end->lte($start)) {
            throw new \InvalidArgumentException('Дата окончания должна быть позже начала.');
        }

        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());
        $days = max(1, $days);

        if ($days < $car->min_days) {
            return [
                'ok' => false,
                'error' => "Это авто сдаём от {$car->min_days} суток.",
                'days' => $days,
            ];
        }

        $seasons = Season::query()->orderBy('sort')->get();
        $prices = $car->prices()->with('season')->get();

        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $cursor = $start->copy()->startOfDay()->addDays($i);
            $season = $this->seasonFor($seasons, $cursor);
            $key = $season?->id ?? 'none';
            $buckets[$key]['season'] = $season;
            $buckets[$key]['days'] = ($buckets[$key]['days'] ?? 0) + 1;
        }

        $lines = [];
        $rentTotal = 0;

        foreach ($buckets as $bucket) {
            $price = $this->priceFor($prices, $bucket['season'], $days);
            if ($price === null) {
                return [
                    'ok' => false,
                    'error' => 'Нет тарифа на выбранные даты.',
                    'days' => $days,
                ];
            }

            $sum = $price * $bucket['days'];
            $rentTotal += $sum;
            $lines[] = [
                'label' => $bucket['season']?->name ?? 'Текущий период',
                'days' => $bucket['days'],
                'price' => $price,
                'sum' => $sum,
            ];
        }

        $nightPickup = $this->isNight($start, $pickup);
        $nightReturn = $this->isNight($end, $return);
        $pickupCost = $pickup?->deliveryPrice($days, $nightPickup) ?? 0;
        $returnCost = $return?->deliveryPrice($days, $nightReturn) ?? 0;

        $extraLines = [];
        $extrasTotal = 0;
        foreach ($extras as $extra) {
            $perDay = (int) ($extra['price_per_day'] ?? 0);
            $count = (int) ($extra['count'] ?? 1);
            $sum = $perDay * $days * $count;
            $extrasTotal += $sum;
            $extraLines[] = [
                'name' => $extra['name'] ?? 'Доп. услуга',
                'sum' => $sum,
            ];
        }

        [$discount, $promo] = $this->promo($promoCode, $car, $days, $rentTotal);
        $total = $rentTotal + $pickupCost + $returnCost + $extrasTotal - $discount;

        $human = collect($lines)
            ->map(fn ($line) => "{$line['days']} сут. × ".$this->money($line['price'])." ({$line['label']})")
            ->implode("\n");

        if ($pickupCost) {
            $human .= "\nВыдача: {$pickup->name} — ".$this->money($pickupCost);
        } elseif ($pickup) {
            $human .= "\nВыдача: {$pickup->name} — бесплатно";
        }

        if ($returnCost) {
            $human .= "\nВозврат: {$return->name} — ".$this->money($returnCost);
        } elseif ($return) {
            $human .= "\nВозврат: {$return->name} — бесплатно";
        }

        if ($discount) {
            $human .= "\nПромокод {$promo['code']}: −".$this->money($discount);
        }

        // Опция «Без залога» среди доп. услуг снимает залог
        $depositWaived = collect($extras)->contains(fn ($extra) => ! empty($extra['waives_deposit']));
        $deposit = $depositWaived ? 0 : (int) $car->deposit;

        $human .= "\nК оплате при получении: ".$this->money($total).'. '.($depositWaived ? 'Без залога.' : 'Залог '.$this->money($deposit).' вернём.');

        return [
            'ok' => true,
            'days' => $days,
            'lines' => $lines,
            'pickup_cost' => $pickupCost,
            'return_cost' => $returnCost,
            'extras' => $extraLines,
            'rent_total' => $rentTotal,
            'discount' => $discount,
            'promo' => $promo,
            'total' => $total,
            'deposit' => $deposit,
            'deposit_waived' => $depositWaived,
            'available' => Availability::isFree($car, $start, $end),
            'human' => $human,
        ];
    }

    /**
     * Только аренда за период, без доставки — для цен «за ваши даты» в каталоге.
     * Сезоны передаются снаружи, чтобы считать пачку машин одним запросом; цены машин — уже загружены.
     *
     * @return array{ok: bool, total?: int, days: int, error?: string}
     */
    public function rentTotal(Car $car, CarbonInterface $start, CarbonInterface $end, ?Collection $seasons = null): array
    {
        $days = max(1, (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()));
        if ($days < $car->min_days) {
            return ['ok' => false, 'days' => $days, 'error' => "от {$car->min_days} сут."];
        }

        $seasons ??= Season::query()->orderBy('sort')->get();
        $prices = $car->relationLoaded('prices') ? $car->prices : $car->prices()->get();
        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $price = $this->priceFor($prices, $this->seasonFor($seasons, $start->copy()->startOfDay()->addDays($i)), $days);
            if ($price === null) {
                return ['ok' => false, 'days' => $days, 'error' => 'по запросу'];
            }
            $total += $price;
        }

        return ['ok' => true, 'total' => $total, 'days' => $days];
    }

    /** Доставка к точке с учётом ночного тарифа — как в quote(). */
    public function deliveryFor(Location $location, CarbonInterface $at, int $days): int
    {
        return $location->deliveryPrice($days, $this->isNight($at, $location));
    }

    /**
     * Скидка по промокоду — только на аренду, не на доставку и доп. услуги.
     *
     * @return array{0: int, 1: array{code: string, ok: bool, label?: string, error?: string}|null}
     */
    private function promo(?string $code, Car $car, int $days, int $rentTotal): array
    {
        if (blank($code)) {
            return [0, null];
        }

        $promo = PromoCode::findByCode($code);
        $reason = $promo ? $promo->rejectionReason($car, $days) : 'Такого промокода нет — проверьте написание.';
        if ($reason) {
            return [0, ['code' => PromoCode::normalize($code), 'ok' => false, 'error' => $reason]];
        }

        return [$promo->discountFor($rentTotal), ['code' => $promo->code, 'ok' => true, 'label' => $promo->label()]];
    }

    private function money(int $n): string
    {
        return number_format($n, 0, ',', "\u{00A0}")."\u{00A0}₽";
    }

    private function seasonFor(Collection $seasons, CarbonInterface $date): ?Season
    {
        return $seasons->first(fn (Season $season) => $season->contains($date));
    }

    private function priceFor(Collection $prices, ?Season $season, int $days): ?int
    {
        $match = $prices
            ->filter(function (CarPrice $price) use ($season, $days) {
                if (! $price->matchesDays($days)) {
                    return false;
                }

                if ($season) {
                    return (int) $price->season_id === (int) $season->id || $price->season_id === null;
                }

                return $price->season_id === null;
            })
            ->sortBy(fn (CarPrice $price) => $price->season_id === null ? 1 : 0)
            ->first();

        return $match?->price;
    }

    private function isNight(CarbonInterface $at, ?Location $location): bool
    {
        if (! $location || ! $location->hours_from || ! $location->hours_to) {
            return $at->hour >= 21 || $at->hour < 8;
        }

        $from = (int) substr($location->hours_from, 0, 2);
        $to = (int) substr($location->hours_to, 0, 2);

        return $at->hour < $from || $at->hour >= $to;
    }
}
