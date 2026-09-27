<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Reviews\Pages\CreateReview;
use App\Filament\Resources\Reviews\Pages\EditReview;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ReviewResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|\UnitEnum|null $navigationGroup = 'Контент';

    protected static ?string $navigationLabel = 'Отзывы';

    protected static ?string $modelLabel = 'отзыв';

    protected static ?string $pluralModelLabel = 'Отзывы';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationBadge(): ?string
    {
        $count = Review::query()->where('is_published', false)->where('source', 'site')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Новые отзывы ждут проверки';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Отзыв')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('author')->label('Имя')->required()->maxLength(80),
                    TextInput::make('city')->label('Город')->maxLength(80),
                    TextInput::make('phone')->label('Телефон')->helperText('На сайте не показывается.')->tel(),
                    Select::make('rating')->label('Оценка')->options([5 => '★★★★★ 5', 4 => '★★★★ 4', 3 => '★★★ 3', 2 => '★★ 2', 1 => '★ 1'])->required()->default(5),
                    Select::make('car_id')->label('Автомобиль')->relationship('car', 'name')->searchable()->preload()->columnSpan(2),
                    Textarea::make('body')->label('Текст отзыва')->required()->rows(6)->columnSpanFull()
                        ->helperText('Можно исправить опечатки, но не смысл. Отзывы не выдумываем — только настоящие.'),
                    Textarea::make('reply')->label('Ответ компании')->rows(3)->columnSpanFull()
                        ->helperText('Показывается под отзывом. Хорошая практика — отвечать на критику.'),
                ]),
            Section::make('Публикация')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('is_published')->label('Опубликован')->inline(false),
                    DateTimePicker::make('reviewed_at')->label('Дата отзыва')->helperText('Заполняется при публикации.'),
                    Select::make('source')->label('Источник')->options(['site' => 'Форма на сайте', 'admin' => 'Добавлен вручную'])->default('admin')->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author')->label('Автор')->searchable()->weight('medium')
                    ->description(fn (Review $review) => $review->city),
                TextColumn::make('rating')->label('Оценка')->sortable()
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state) => $state >= 4 ? 'success' : ($state === 3 ? 'warning' : 'danger')),
                TextColumn::make('body')->label('Отзыв')->limit(90)->wrap()->searchable(),
                TextColumn::make('car.name')->label('Автомобиль')->toggleable(),
                IconColumn::make('is_published')->label('На сайте')->boolean(),
                IconColumn::make('reply')->label('Ответ')->boolean()->state(fn (Review $review) => filled($review->reply))->toggleable(),
                TextColumn::make('created_at')->label('Получен')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')->label('Опубликован')->trueLabel('На сайте')->falseLabel('На проверке'),
                SelectFilter::make('rating')->label('Оценка')->options([5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']),
            ])
            ->recordActions([
                Action::make('publish')->label('Опубликовать')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (Review $review) => ! $review->is_published)
                    ->action(fn (Review $review) => $review->update(['is_published' => true])),
                Action::make('hide')->label('Скрыть')->icon(Heroicon::OutlinedEyeSlash)->color('gray')
                    ->visible(fn (Review $review) => $review->is_published)
                    ->action(fn (Review $review) => $review->update(['is_published' => false])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')->label('Опубликовать')->icon(Heroicon::OutlinedCheck)
                        ->action(fn (Collection $records) => $records->each->update(['is_published' => true])),
                    BulkAction::make('hide')->label('Скрыть')->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each->update(['is_published' => false])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
            'create' => CreateReview::route('/create'),
            'edit' => EditReview::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'reviews';
    }
}
