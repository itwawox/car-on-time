<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class FaqResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Контент';

    protected static ?string $navigationLabel = 'Вопросы и ответы';

    protected static ?string $modelLabel = 'вопрос';

    protected static ?string $pluralModelLabel = 'Вопросы и ответы';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Вопрос')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('question')->label('Вопрос')->required()->maxLength(255)->columnSpanFull()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Set $set, $record) => $record?->slug ? null : $set('slug', Str::slug(Str::limit((string) $state, 60, '')))),
                    Textarea::make('answer')->label('Ответ')->required()->rows(6)->columnSpanFull()
                        ->helperText('Новая строка — новый абзац. Пишите фактами из условий аренды: цифры клиенты сверяют с договором.'),
                    TextInput::make('link_url')->label('Ссылка под ответом')->placeholder('/usloviya'),
                    TextInput::make('link_label')->label('Текст ссылки')->placeholder('Все условия аренды'),
                ]),
            Section::make('Где показывать')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('group')->label('Раздел')->options(Faq::GROUPS)->required()->default('booking'),
                    TextInput::make('slug')->label('Якорь ссылки')->prefix('/faq#q-')->alphaDash()->unique(ignoreRecord: true),
                    TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                    Toggle::make('is_published')->label('Опубликован')->default(true)->inline(false),
                    Toggle::make('is_featured')->label('На главной')->inline(false)->helperText('До 8 вопросов.'),
                    Toggle::make('show_on_car')->label('В карточке машины')->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')->label('Вопрос')->searchable()->wrap()->weight('medium')
                    ->description(fn (Faq $f) => Str::limit($f->answer, 90)),
                TextColumn::make('group')->label('Раздел')->badge()->formatStateUsing(fn ($state) => Faq::GROUPS[$state] ?? $state),
                ToggleColumn::make('is_featured')->label('Главная'),
                ToggleColumn::make('show_on_car')->label('Карточка'),
                ToggleColumn::make('is_published')->label('Опубл.'),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([
                SelectFilter::make('group')->label('Раздел')->options(Faq::GROUPS),
                TernaryFilter::make('is_featured')->label('На главной'),
                TernaryFilter::make('show_on_car')->label('В карточке машины'),
            ])
            ->recordActions([
                Action::make('open')->label('')->tooltip('Открыть на сайте')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Faq $f) => route('faq').'#'.$f->anchor(), true),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')->label('Опубликовать')->icon(Heroicon::OutlinedEye)
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
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
