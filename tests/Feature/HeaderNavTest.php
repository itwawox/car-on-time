<?php

use App\Models\Article;

// «Статьи» — только в подвале: главное меню в шапке и мобильное меню короче

it('shows articles link in the footer but not in the header or mobile menu', function () {
    Article::query()->create(['slug' => 'aeroport', 'title' => 'Как взять авто в аэропорту', 'content' => '<p>Текст</p>', 'is_published' => true, 'published_at' => now()->subMinute()]);

    $html = $this->get('/')->assertOk()->getContent();
    $link = 'href="'.route('articles').'"';
    $nav = fn (string $label): string => Str::before(Str::after($html, 'aria-label="'.$label.'"'), '</nav>');

    expect($nav('Основное меню'))->not->toContain($link)
        ->and($nav('Мобильное меню'))->not->toContain($link)
        ->and($nav('Информация'))->toContain($link);
});
