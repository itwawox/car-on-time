<?php

use App\Filament\Pages\CourseDocs;
use App\Filament\Pages\DeveloperDocs;
use App\Models\User;
use App\Support\DocsSearch;
use Livewire\Livewire;

// Поиск по курсу и документации прощает окончания, опечатки, транслит и не ту раскладку

function docsTitles(string $query): array
{
    return array_column(DocsSearch::search($query, [CourseDocs::class, DeveloperDocs::class], limit: 100), 'title');
}

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('finds lessons by another word form, typo, translit and wrong keyboard layout', function (string $query, string $title) {
    expect(docsTitles($query))->toContain($title);
})->with([
    'word form' => ['тестов', 'Что такое тесты и как они работают'],
    'typo' => ['миграцыи', 'Структура проекта'],
    'translit' => ['ларастан', 'Larastan — поиск ошибок без запуска'],
    'wrong layout' => ['дфкфыефт', 'Larastan — поиск ошибок без запуска'],
]);

it('searches developer docs too and shows where the match is', function () {
    $results = DocsSearch::search('baseline', [CourseDocs::class, DeveloperDocs::class]);

    expect(array_column($results, 'book'))->toContain('Курс', 'Для разработчиков')
        ->and((string) $results[0]['snippet'])->toContain('<mark>');
});

it('shows results on the page and says when nothing is found', function () {
    Livewire::test(CourseDocs::class)
        ->set('q', 'ларастан')
        ->assertSee('Нашлось')
        ->assertSee('Larastan — поиск ошибок без запуска')
        ->set('q', 'йцукенгшщз')
        ->assertSee('Ничего не нашлось');
});
