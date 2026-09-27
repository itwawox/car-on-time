@php $rows = $this->rows(); $fmt = fn ($n) => number_format($n, 0, ',', ' ').' ₽'; @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Партнёры: выручка и комиссия" description="Подтверждённые заявки с начала месяца">
        @if($rows->isEmpty())
            <p style="opacity:.7">Подтверждённых заявок в этом месяце пока нет.</p>
        @else
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <thead><tr style="text-align:left;opacity:.7"><th style="padding:6px 0">Партнёр</th><th>Аренд</th><th style="text-align:right">Выручка</th><th style="text-align:right">Комиссия</th></tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr style="border-top:1px solid var(--gray-200)">
                            <td style="padding:6px 0">{{ $row['partner'] }}</td>
                            <td>{{ $row['bookings'] }}</td>
                            <td style="text-align:right">{{ $fmt($row['revenue']) }}</td>
                            <td style="text-align:right;font-weight:600">{{ $fmt($row['commission']) }}</td>
                        </tr>
                    @endforeach
                    <tr style="border-top:2px solid var(--gray-300);font-weight:700">
                        <td style="padding:6px 0">Итого</td><td>{{ $rows->sum('bookings') }}</td>
                        <td style="text-align:right">{{ $fmt($rows->sum('revenue')) }}</td><td style="text-align:right">{{ $fmt($rows->sum('commission')) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
