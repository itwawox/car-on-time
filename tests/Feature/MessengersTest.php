<?php

use App\Models\Setting;

// ВКонтакте — рядом с мессенджерами там, где блок выводится иконками (карточка машины, подвал)

beforeEach(function () {
    Setting::put('whatsapp', 'https://wa.me/79780000000');
    Setting::put('vk', 'https://vk.com/car_on_time');
});

it('adds VK to the messengers block only when asked', function () {
    expect(view('partials.messengers', ['variant' => 'icons', 'withVk' => true])->render())
        ->toContain('https://vk.com/car_on_time')->toContain('https://wa.me/79780000000')
        ->and(view('partials.messengers', ['variant' => 'grid'])->render())
        ->not->toContain('https://vk.com/car_on_time');
});

it('shows VK in the footer exactly once', function () {
    expect(substr_count($this->get('/')->assertOk()->getContent(), 'href="https://vk.com/car_on_time"'))->toBe(1);
});
