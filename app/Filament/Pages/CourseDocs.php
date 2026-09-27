<?php

namespace App\Filament\Pages;

use Filament\Support\Icons\Heroicon;

/**
 * Курс для владельца: из чего состоит сайт, как работают тесты, Pest, Pint и Larastan — на примерах этого проекта.
 * Уроки лежат в resources/docs/course/*.md.
 */
class CourseDocs extends MarkdownDocsPage
{
    protected static ?string $navigationLabel = 'Курс';

    protected static ?string $title = 'Курс: как устроен и проверяется сайт';

    protected static ?string $slug = 'course';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static function folder(): string
    {
        return 'docs/course';
    }
}
