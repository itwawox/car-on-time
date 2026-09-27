<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Сотрудники с доступом в админку и их роли. */
class UserResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Сотрудники';

    protected static ?string $modelLabel = 'сотрудник';

    protected static ?string $pluralModelLabel = 'Сотрудники';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Имя')->required()->maxLength(120),
                TextInput::make('email')->label('Email для входа')->email()->required()->unique(ignoreRecord: true),
                Select::make('role')->label('Роль')->options(User::ROLES)->required()->default('manager')
                    // Себя владелец не понижает: иначе можно остаться без доступа к ролям
                    ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                Toggle::make('is_active')->label('Доступ открыт')->default(true)->inline(false)
                    ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                TextInput::make('password')->label('Пароль')->password()->revealable()
                    ->minLength(10)
                    ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn ($livewire) => $livewire instanceof CreateRecord ? 'Не короче 10 символов.' : 'Пусто — пароль не меняется.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Имя')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('role')->label('Роль')->badge()
                    ->formatStateUsing(fn ($state) => str(User::ROLES[$state] ?? $state)->before(' —')->toString())
                    ->color(fn ($state) => ['owner' => 'danger', 'manager' => 'info', 'editor' => 'success'][$state] ?? 'gray'),
                IconColumn::make('is_active')->label('Доступ')->boolean(),
                TextColumn::make('created_at')->label('Добавлен')->date('d.m.Y')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    protected static function accessArea(): string
    {
        return 'admin';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
