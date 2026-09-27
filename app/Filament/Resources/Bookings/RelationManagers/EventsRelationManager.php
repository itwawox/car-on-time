<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Models\Booking;
use App\Models\BookingEvent;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** История заявки: кто и когда менял статус, заметки, эскалации. */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'История';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('comment')->label('Заметка')->required()->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i'),
                TextColumn::make('type')->label('Событие')->badge()
                    ->formatStateUsing(fn ($state) => BookingEvent::TYPES[$state] ?? $state)
                    ->color(fn ($state) => ['escalated' => 'danger', 'note' => 'gray', 'created' => 'warning'][$state] ?? 'info'),
                TextColumn::make('change')->label('Что')->wrap()
                    ->state(fn (BookingEvent $e) => $e->type === 'status'
                        ? (Booking::STATUSES[$e->from] ?? $e->from).' → '.(Booking::STATUSES[$e->to] ?? $e->to)
                        : $e->comment),
                TextColumn::make('user.name')->label('Кто')->placeholder('Сайт'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->headerActions([
                CreateAction::make()->label('Добавить заметку')->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateDataUsing(fn (array $data) => [...$data, 'type' => 'note', 'user_id' => auth()->id()]),
            ]);
    }
}
