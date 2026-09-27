<?php

namespace App\Filament\Pages;

use Filament\Support\Icons\Heroicon;

/**
 * Документация для разработчиков: как устроен сайт, как выкатывать обновления, миграции, файлы, откат.
 * Тексты лежат в resources/docs/developers/*.md — меняются вместе с кодом, через GitHub.
 */
class DeveloperDocs extends MarkdownDocsPage
{
    protected static ?string $navigationLabel = 'Для разработчиков';

    protected static ?string $title = 'Документация для разработчиков';

    protected static ?string $slug = 'docs';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static function folder(): string
    {
        return 'docs/developers';
    }
}
