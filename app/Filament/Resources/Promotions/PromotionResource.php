<?php

namespace App\Filament\Resources\Promotions;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Models\Promotion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PromotionResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|\UnitEnum|null $navigationGroup = 'Контент';

    protected static ?string $navigationLabel = 'Акции';

    protected static ?string $modelLabel = 'акция';

    protected static ?string $pluralModelLabel = 'Акции';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Акция')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->label('Название')->required()->maxLength(160)->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Set $set, $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                    TextInput::make('slug')->label('Адрес')->prefix('/akcii/')->required()->alphaDash()->unique(ignoreRecord: true),
                    TextInput::make('badge')->label('Плашка')->placeholder('−10%')->maxLength(40),
                    TextInput::make('promo_code')->label('Промокод')->placeholder('SUMMER')->maxLength(40)
                        ->helperText('Клиент вводит его в заявке, скидку подтверждает менеджер.'),
                    Textarea::make('excerpt')->label('Коротко (для карточки)')->rows(2)->columnSpanFull(),
                    RichEditor::make('body')->label('Условия акции')->columnSpanFull()
                        ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3', 'bulletList', 'orderedList'], ['undo', 'redo']]),
                    FileUpload::make('cover')->label('Обложка')->image()->disk('public')->directory('promotions')->columnSpanFull()
                        ->helperText('16:9, от 1200 px. Копии webp создаются автоматически.'),
                ]),
            Section::make('Публикация')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('is_published')->label('Опубликована')->inline(false),
                    DateTimePicker::make('starts_at')->label('Начало')->helperText('Пусто — сразу.'),
                    DateTimePicker::make('ends_at')->label('Окончание')->helperText('Пусто — бессрочно. После окончания страница скрывается.'),
                    TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                ]),
            Section::make('SEO')
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('seo_title')->label('Title'),
                    Textarea::make('seo_description')->label('Description')->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')->label('')->disk('public')->width(64)->height(36),
                TextColumn::make('title')->label('Акция')->searchable()->weight('medium')->description(fn (Promotion $p) => $p->periodLabel()),
                TextColumn::make('badge')->label('Плашка')->badge(),
                TextColumn::make('promo_code')->label('Промокод')->copyable(),
                IconColumn::make('is_published')->label('Опубл.')->boolean(),
                TextColumn::make('ends_at')->label('До')->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                Action::make('open')->label('')->tooltip('Открыть на сайте')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Promotion $p) => route('promotion', $p->slug), true),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
