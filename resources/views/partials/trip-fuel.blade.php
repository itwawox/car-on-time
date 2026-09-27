{{-- «Бензин на поездку»: справка под характеристиками машины. Считает initTrip() в app.js, итог аренды берёт из расчёта заявки. --}}
@if($per100 = $car->facts()->costPer100km())
    @php($tripRoutes = \App\Support\TripCost::routes())
    <div class="trip trip-fuel" data-trip data-per100="{{ $per100 }}"
         data-consumption="{{ \App\Support\CarFacts::num($car->facts()->consumption) }}"
         data-unit="{{ $car->facts()->unit() }}" data-grade="{{ $car->facts()->gradeLabel() }}"
         data-price="{{ \App\Support\CarFacts::num($car->facts()->fuelPrice()) }}">
        <label class="field-label" for="trip-route">@include('partials.icon', ['name' => 'fuel', 'size' => 14]) {{ \App\Models\Setting::get('trip_title') ?: ($car->facts()->isElectric() ? 'Зарядка на поездку' : 'Бензин на поездку') }}</label>
        <div class="trip-row">
            <select class="field" id="trip-route" data-trip-route>
                @foreach($tripRoutes as $r)
                    <option value="{{ $r['km'] }}" data-per-day="{{ $r['per_day'] ? 1 : 0 }}">{{ $r['name'] }} · ≈{{ $r['km'] }} км{{ $r['per_day'] ? ' в день' : '' }}</option>
                @endforeach
                <option value="custom">Свой пробег…</option>
            </select>
            <input class="field trip-km" type="number" min="1" max="20000" step="10" inputmode="numeric" placeholder="км" aria-label="Пробег, км" data-trip-km hidden>
        </div>
        <p class="trip-out" data-trip-out aria-live="polite">≈ {{ number_format($per100, 0, ',', ' ') }} ₽ на 100 км</p>
        <p class="trip-total" data-trip-total hidden></p>
    </div>
@endif
