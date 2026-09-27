@php($result = $this->getResult())
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Запрос посетителя</x-slot>
        <x-slot name="description">Пишите как посетитель: с опечатками, транслитом, в другой раскладке. Результат — ровно как на сайте.</x-slot>

        <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
            <x-filament::input type="search" wire:model.live.debounce.350ms="query" placeholder="Например: хундай крета автомат до 3000" autofocus />
        </x-filament::input.wrapper>

        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px">
            @foreach((array) (\App\Support\Search\SearchSettings::ui()['examples'] ?? []) as $example)
                <x-filament::badge tag="button" type="button" color="gray" wire:click="$set('query', {{ \Illuminate\Support\Js::from((string) $example) }})">{{ $example }}</x-filament::badge>
            @endforeach
        </div>
    </x-filament::section>

    @if($result)
        <x-filament::section>
            <x-slot name="heading">Как поиск понял запрос</x-slot>

            <dl style="display:grid;grid-template-columns:max-content 1fr;gap:10px 20px;margin:0;font-size:.875rem">
                <dt style="color:#6b7280">Найдено</dt>
                <dd style="margin:0;font-weight:600">{{ $result['total'] }} авто</dd>

                @if($result['meta']['corrected'])
                    <dt style="color:#6b7280">Раскладка</dt>
                    <dd style="margin:0"><x-filament::badge color="warning" style="display:inline-flex">исправлена → {{ $result['meta']['corrected'] }}</x-filament::badge></dd>
                @endif

                @if($result['meta']['relaxed'])
                    <dt style="color:#6b7280">Режим</dt>
                    <dd style="margin:0"><x-filament::badge color="warning" style="display:inline-flex">частичные совпадения — не все слова найдены</x-filament::badge></dd>
                @endif

                <dt style="color:#6b7280">Слова для поиска</dt>
                <dd style="margin:0;display:flex;flex-wrap:wrap;gap:6px">
                    @forelse($result['terms'] as $term)
                        <x-filament::badge color="gray" tooltip="Фонетический ключ: {{ $term['key'] }}">{{ $term['word'] }} <span style="opacity:.6">· {{ $term['key'] }}</span></x-filament::badge>
                    @empty
                        <span style="color:#6b7280">нет — только фильтры</span>
                    @endforelse
                </dd>

                <dt style="color:#6b7280">Фильтры</dt>
                <dd style="margin:0;display:flex;flex-wrap:wrap;gap:6px">
                    @forelse($result['meta']['chips'] as $chip)
                        <x-filament::badge color="primary">{{ $chip['label'] }}</x-filament::badge>
                    @empty
                        <span style="color:#6b7280">нет</span>
                    @endforelse
                </dd>

                <dt style="color:#6b7280">Разделы в подсказках</dt>
                <dd style="margin:0;display:flex;flex-wrap:wrap;gap:6px">
                    @forelse($result['links'] as $link)
                        <x-filament::badge color="success">{{ $link['title'] }} · {{ $link['subtitle'] }}</x-filament::badge>
                    @empty
                        <span style="color:#6b7280">нет</span>
                    @endforelse
                </dd>
            </dl>

            @if(! $result['total'])
                <p style="margin:16px 0 0;font-size:.875rem;color:#6b7280">
                    Не нашлось? Добавьте написание в «Синонимы для поиска» у нужной марки, класса или машины, или в раздел
                    <x-filament::link :href="\App\Filament\Resources\SearchSynonyms\SearchSynonymResource::getUrl('create')">«Синонимы слов»</x-filament::link>.
                </p>
            @endif
        </x-filament::section>

        @if($result['rows'])
            <x-filament::section>
                <x-slot name="heading">Выдача (первые {{ count($result['rows']) }})</x-slot>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                        <thead>
                            <tr style="text-align:left;color:#6b7280;border-bottom:1px solid rgb(0 0 0 / .08)">
                                <th style="padding:8px 8px 8px 0;font-weight:500">#</th>
                                <th style="padding:8px;font-weight:500">Машина</th>
                                <th style="padding:8px;font-weight:500">Очки</th>
                                <th style="padding:8px;font-weight:500">Коробка</th>
                                <th style="padding:8px;font-weight:500">Класс</th>
                                <th style="padding:8px;font-weight:500;text-align:right">Цена от</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($result['rows'] as $i => $row)
                                <tr style="border-bottom:1px solid rgb(0 0 0 / .05)">
                                    <td style="padding:8px 8px 8px 0;color:#9ca3af">{{ $i + 1 }}</td>
                                    <td style="padding:8px"><x-filament::link :href="$row['url']">{{ $row['name'] }}</x-filament::link></td>
                                    <td style="padding:8px;font-variant-numeric:tabular-nums">{{ number_format($row['score'], 2, ',', ' ') }}</td>
                                    <td style="padding:8px">{{ $row['gearbox'] }}</td>
                                    <td style="padding:8px">{{ $row['class'] }}</td>
                                    <td style="padding:8px;text-align:right;font-variant-numeric:tabular-nums">{{ $row['price'] ? number_format($row['price'], 0, ',', ' ').' ₽' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
