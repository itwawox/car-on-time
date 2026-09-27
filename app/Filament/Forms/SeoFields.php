<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * Блок SEO-полей для карточек: марки, классы, кузова, города, статьи.
 * Пустые поля заполняются шаблонами из «SEO → Настройки SEO».
 */
class SeoFields
{
    /**
     * @param  list<string>  $only  какие поля показать: h1, seo_title, seo_description, intro, seo_text
     */
    public static function section(array $only = ['h1', 'seo_title', 'seo_description', 'intro', 'seo_text']): Section
    {
        $fields = [
            'h1' => TextInput::make('h1')->label('H1')->maxLength(255),
            'seo_title' => TextInput::make('seo_title')
                ->label('Title')
                ->maxLength(255)
                ->helperText(fn (?string $state) => 'Оптимально 50–65 символов. Сейчас: '.mb_strlen((string) $state).'.')
                ->live(onBlur: true),
            'seo_description' => Textarea::make('seo_description')
                ->label('Description')
                ->rows(2)
                ->helperText(fn (?string $state) => 'Оптимально 120–160 символов. Сейчас: '.mb_strlen((string) $state).'.')
                ->live(onBlur: true)
                ->columnSpanFull(),
            'intro' => Textarea::make('intro')->label('Вступление под H1')->rows(3)->columnSpanFull(),
            'seo_text' => RichEditor::make('seo_text')
                ->label('SEO-текст внизу страницы')
                ->helperText('Уникальный текст о разделе: что за машины, для каких поездок, цены, условия. От 1 500 знаков. Показывается только на первой странице.')
                ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                ->columnSpanFull(),
        ];

        return Section::make('SEO')
            ->description('Пустое поле — берётся шаблон из «SEO → Настройки SEO».')
            ->collapsible()
            ->columns(2)
            ->columnSpanFull()
            ->schema(array_values(array_intersect_key($fields, array_flip($only))));
    }
}
