<?php

namespace App\Filament\Resources\Cars\RelationManagers;

use App\Models\CarBlock;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Занятость машины: подтверждённые брони (ставятся сами) и ручные блокировки. */
class BlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'blocks';

    protected static ?string $title = 'Занятость';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DateTimePicker::make('starts_at')->label('С')->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('По')->seconds(false)->required()->after('starts_at'),
            TextInput::make('reason')->label('Причина')->placeholder('Ремонт, у владельца, аренда вне сайта…')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('ends_at', '>=', now()->subDays(7)))
            ->columns([
                TextColumn::make('starts_at')->label('С')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('ends_at')->label('По')->dateTime('d.m.Y H:i'),
                TextColumn::make('reason')->label('Причина')->badge()
                    ->color(fn (CarBlock $b) => $b->booking_id ? 'success' : 'gray'),
            ])
            ->defaultSort('starts_at')
            ->headerActions([CreateAction::make()->label('Заблокировать даты')])
            ->recordActions([
                // Блок брони меняется только через статус заявки — иначе календарь разойдётся с заявками
                EditAction::make()->visible(fn (CarBlock $b) => ! $b->booking_id),
                DeleteAction::make()->visible(fn (CarBlock $b) => ! $b->booking_id),
            ]);
    }
}
