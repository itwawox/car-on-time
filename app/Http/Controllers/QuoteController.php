<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Location;
use App\Models\Season;
use App\Services\QuoteCalculator;
use App\Support\Availability;
use App\Support\Places;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class QuoteController extends Controller
{
    public function __invoke(Request $request, QuoteCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'car_id' => ['required', 'exists:cars,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'pickup_location_id' => ['nullable', 'exists:locations,id'],
            'return_location_id' => ['nullable', 'exists:locations,id'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'exists:extras,id'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $car = Car::query()->with('prices.season')->findOrFail($data['car_id']);

        try {
            $quote = $calculator->quote(
                $car,
                Carbon::parse($data['starts_at']),
                Carbon::parse($data['ends_at']),
                isset($data['pickup_location_id']) ? Location::query()->find($data['pickup_location_id']) : null,
                isset($data['return_location_id']) ? Location::query()->find($data['return_location_id']) : null,
                BookingController::extras($data['extras'] ?? []),
                $data['promo_code'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        // Короткие названия точек для «чека» в карточке машины: «Симферополь, аэропорт»
        if ($quote['ok'] ?? false) {
            $places = collect(Places::all())->keyBy('id');
            $name = fn ($id) => ($p = $places[(int) $id] ?? null) ? ($p['label'] ? $p['group'].', '.$p['label'] : $p['group']) : null;
            $quote['pickup_name'] = $name($data['pickup_location_id'] ?? null);
            $quote['return_name'] = $name($data['return_location_id'] ?? null);
        }

        return response()->json($quote);
    }

    /** Цены за период для пачки машин каталога: {id: {ok, total, days}}. */
    public function batch(Request $request, QuoteCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'string', 'max:400'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'pickup_location_id' => ['nullable', 'integer'],
            'return_location_id' => ['nullable', 'integer'],
        ]);

        $ids = collect(explode(',', $data['ids']))->map(fn ($id) => (int) $id)->filter()->unique()->take(48);
        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);
        $seasons = Season::query()->orderBy('sort')->get();

        // Доставка к выбранной точке — так же, как в полном расчёте заявки
        $pickup = isset($data['pickup_location_id']) ? Location::query()->find($data['pickup_location_id']) : null;
        $return = isset($data['return_location_id']) ? Location::query()->find($data['return_location_id']) : $pickup;
        $delivery = fn (int $days) => ($pickup ? $calculator->deliveryFor($pickup, $start, $days) : 0) + ($return ? $calculator->deliveryFor($return, $end, $days) : 0);

        $busy = array_flip(Availability::busyCarIds($start, $end, $ids));

        $result = Car::query()->published()->whereIn('id', $ids)->with('prices')->get()
            ->mapWithKeys(function (Car $car) use ($calculator, $start, $end, $seasons, $delivery, $busy) {
                $quote = $calculator->rentTotal($car, $start, $end, $seasons);
                if ($quote['ok']) {
                    $quote['delivery'] = $delivery($quote['days']);
                }
                $quote['available'] = ! isset($busy[$car->id]);

                return [$car->id => $quote];
            });

        return response()->json(['ok' => true, 'prices' => $result]);
    }
}
