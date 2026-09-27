<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Заявка по телефону')];
    }

    public function getTabs(): array
    {
        $count = fn (array $statuses) => Booking::query()->whereIn('status', $statuses)->count();

        return [
            'new' => Tab::make('Новые')->badge($count(['new']) ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'new')),
            'progress' => Tab::make('В работе')->badge($count(Booking::IN_PROGRESS) ?: null)->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', Booking::IN_PROGRESS)),
            'upcoming' => Tab::make('Ближайшие выдачи')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'confirmed')->where('starts_at', '>=', now()->startOfDay())->reorder('starts_at')),
            'confirmed' => Tab::make('Подтверждены')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'confirmed')),
            'declined' => Tab::make('Отказы')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'declined')),
            'all' => Tab::make('Все'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return Booking::query()->where('status', 'new')->exists() ? 'new' : 'progress';
    }
}
