<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Forms\SeoFields;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Car;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ArticleResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Статьи';

    protected static ?string $modelLabel = 'статья';

    protected static ?string $pluralModelLabel = 'Статьи';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Tabs::make('article')->columnSpan(['lg' => 2])->tabs([
                    Tab::make('Текст')->icon(Heroicon::OutlinedPencilSquare)->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set, ?Article $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                        Textarea::make('excerpt')
                            ->label('Анонс')
                            ->helperText('1–2 предложения под заголовком и в карточке статьи. Если description пустой — идёт и туда.')
                            ->rows(2),
                        RichEditor::make('content')
                            ->label('Текст статьи')
                            ->helperText('Структурируйте подзаголовками H2 — по ним автоматически строится оглавление. Хорошая статья — от 5 000 знаков, с конкретикой: цены, расстояния, время в пути.')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('articles/content')
                            ->toolbarButtons([
                                ['bold', 'italic', 'link'],
                                ['h2', 'h3', 'blockquote'],
                                ['bulletList', 'orderedList', 'table'],
                                ['attachFiles'],
                                ['undo', 'redo'],
                            ])
                            ->required(),
                    ]),
                    Tab::make('Вопросы и ответы')->icon(Heroicon::OutlinedQuestionMarkCircle)->schema([
                        Repeater::make('faq')
                            ->hiddenLabel()
                            ->helperText('Показываются после статьи и попадают в разметку FAQ — Яндекс и Google могут вывести их прямо в выдаче.')
                            ->schema([
                                TextInput::make('question')->label('Вопрос')->required(),
                                Textarea::make('answer')->label('Ответ')->rows(3)->required(),
                            ])
                            ->addActionLabel('Добавить вопрос')
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['question'] ?? null)
                            ->defaultItems(0),
                    ]),
                    Tab::make('Подборка машин')->icon(Heroicon::OutlinedTruck)->schema([
                        TextInput::make('cars_query')
                            ->label('Какие машины показать под статьёй')
                            ->helperText(fn (Get $get) => 'Запрос для умного поиска: «кроссовер автомат», «7 мест», «кабриолет». '.self::carsPreview($get('cars_query')))
                            ->live(debounce: 600),
                    ]),
                    Tab::make('SEO')->icon(Heroicon::OutlinedPresentationChartLine)->schema([
                        SeoFields::section(['h1', 'seo_title', 'seo_description']),
                    ]),
                ]),
                Grid::make(1)->columnSpan(['lg' => 1])->schema([
                    Section::make('Публикация')->schema([
                        Toggle::make('is_published')->label('Опубликована')->default(false),
                        DateTimePicker::make('published_at')
                            ->label('Дата публикации')
                            ->helperText('Можно поставить в будущее — статья появится сама.')
                            ->default(now())
                            ->seconds(false),
                        TextInput::make('slug')
                            ->label('Адрес')
                            ->prefix('/stati/')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Select::make('category')
                            ->label('Рубрика')
                            ->options(array_combine(Article::CATEGORIES, Article::CATEGORIES)),
                    ]),
                    Section::make('Обложка')->schema([
                        FileUpload::make('cover')
                            ->hiddenLabel()
                            ->image()
                            ->disk('public')
                            ->directory('articles')
                            ->imageEditor()
                            ->imageEditorAspectRatios(['16:9'])
                            ->helperText('От 1200×675 px. Автоматически сожмётся в webp.'),
                        TextInput::make('cover_alt')->label('Описание картинки (alt)'),
                    ]),
                    Section::make('Автор')
                        ->description('Статьи с живым автором поисковики ценят выше (E-E-A-T).')
                        ->schema([
                            TextInput::make('author_name')->label('Имя'),
                            TextInput::make('author_role')->label('Кто он')->placeholder('Менеджер проката, 8 лет в Крыму'),
                        ]),
                ]),
            ]),
        ]);
    }

    private static function carsPreview(?string $query): string
    {
        if (blank($query)) {
            return '';
        }

        $total = Car::search($query)->raw()['total'];

        return $total ? "Найдётся машин: {$total}, покажем 6." : 'Сейчас по этому запросу машин нет.';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')->label('')->disk('public')->width(72)->height(40),
                TextColumn::make('title')->label('Заголовок')->searchable()->weight('medium')->wrap()
                    ->description(fn (Article $r) => '/stati/'.$r->slug),
                TextColumn::make('category')->label('Рубрика')->badge()->color('gray'),
                IconColumn::make('is_published')->label('Опубл.')->boolean(),
                TextColumn::make('published_at')->label('Дата')->date('d.m.Y')->sortable(),
                TextColumn::make('content')->label('Знаков')
                    ->formatStateUsing(fn ($state) => number_format(mb_strlen(strip_tags((string) $state)), 0, ',', ' '))
                    ->color(fn ($state) => mb_strlen(strip_tags((string) $state)) < 3000 ? 'danger' : 'success')
                    ->tooltip('Меньше 3 000 знаков — поисковики могут счесть статью малоценной'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')->label('Опубликована'),
                SelectFilter::make('category')->label('Рубрика')->options(array_combine(Article::CATEGORIES, Article::CATEGORIES)),
            ])
            ->recordActions([
                Action::make('open')->label('Открыть')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Article $r) => $r->url(), true)
                    ->visible(fn (Article $r) => $r->is_published),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
