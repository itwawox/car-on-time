<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\TagsInput;

/**
 * Поле «Синонимы для поиска» для марок, классов, кузовов и машин.
 * Хранится строкой через запятую — так же, как её читает индекс поиска.
 */
class SearchAliasesInput
{
    public static function make(string $helperText): TagsInput
    {
        return TagsInput::make('search_aliases')
            ->label('Синонимы для поиска')
            ->placeholder('Добавьте написание и нажмите Enter')
            ->separator(', ')
            ->splitKeys([',', 'Enter'])
            ->helperText($helperText)
            ->columnSpanFull();
    }
}
