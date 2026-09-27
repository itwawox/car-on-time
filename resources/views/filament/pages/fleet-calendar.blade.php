@php
    $days = $this->days();
    $rows = $this->rows();
    $colors = [
        'booking' => 'var(--success-500)',
        'block' => 'var(--gray-400)',
        'request' => 'var(--warning-400)',
    ];
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:8px;align-items:center">
            <x-filament::button color="gray" size="sm" wire:click="shift(-7)" icon="heroicon-m-chevron-left">Неделя</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="today">Сегодня</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="shift(7)" icon="heroicon-m-chevron-right" icon-position="after">Неделя</x-filament::button>
        </div>
        <div style="width:260px">
            <x-filament::input.wrapper>
                <x-filament::input type="search" wire:model.live.debounce.400ms="search" placeholder="Найти машину" />
            </x-filament::input.wrapper>
        </div>
        <div style="display:flex;gap:14px;font-size:.8rem;align-items:center">
            <span><i style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $colors['booking'] }};vertical-align:middle"></i> Бронь</span>
            <span><i style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $colors['request'] }};vertical-align:middle"></i> Заявка в работе</span>
            <span><i style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $colors['block'] }};vertical-align:middle"></i> Блокировка</span>
            <strong>Загрузка: {{ $this->utilization($rows) }}%</strong>
        </div>
    </div>

    <div style="overflow-x:auto;border:1px solid var(--gray-200);border-radius:12px" class="dark:border-white/10">
        <table style="border-collapse:separate;border-spacing:0;width:100%;font-size:.75rem">
            <thead>
                <tr>
                    <th style="position:sticky;left:0;z-index:1;text-align:left;padding:8px 10px;min-width:220px" class="bg-white dark:bg-gray-900">Машина</th>
                    @foreach($days as $day)
                        <th style="padding:6px 2px;min-width:30px;text-align:center;font-weight:{{ $day->isToday() ? 800 : 500 }};{{ $day->isWeekend() ? 'color:var(--danger-600)' : '' }}">
                            {{ $day->format('d') }}<br><span style="opacity:.6">{{ ['вс','пн','вт','ср','чт','пт','сб'][$day->dayOfWeek] }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td style="position:sticky;left:0;padding:6px 10px;border-top:1px solid var(--gray-100);white-space:nowrap" class="bg-white dark:bg-gray-900">
                            <a href="{{ \App\Filament\Resources\Cars\CarResource::getUrl('edit', ['record' => $row['car']]) }}" style="font-weight:600">{{ $row['car']->name }}</a>
                        </td>
                        @foreach($row['cells'] as $cell)
                            <td style="padding:2px;border-top:1px solid var(--gray-100)">
                                @if($cell['kind'])
                                    @if($cell['url'])<a href="{{ $cell['url'] }}" title="{{ $cell['label'] }}" style="display:block;height:22px;border-radius:4px;background:{{ $colors[$cell['kind']] }}"></a>
                                    @else<span title="{{ $cell['label'] }}" style="display:block;height:22px;border-radius:4px;background:{{ $colors[$cell['kind']] }}"></span>@endif
                                @else
                                    <span style="display:block;height:22px;border-radius:4px;background:var(--gray-50)" class="dark:bg-white/5"></span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($days) + 1 }}" style="padding:24px;text-align:center">Машины не найдены</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
