<?php

use App\Support\Attribution;
use App\Support\Phone;

it('normalizes russian phone numbers to +7', function (string $input, string $expected) {
    expect(Phone::e164($input))->toBe($expected);
})->with([
    'with brackets' => ['+7 (978) 948-48-48', '+79789484848'],
    'starting with 8' => ['8 978 948 48 48', '+79789484848'],
    'ten digits' => ['9789484848', '+79789484848'],
]);

it('labels traffic source for managers', function (?array $utm, ?string $expected) {
    expect(Attribution::label($utm))->toBe($expected);
})->with([
    'utm' => [['utm_source' => 'yandex', 'utm_medium' => 'cpc', 'utm_campaign' => 'leto'], 'yandex / cpc / leto'],
    'yandex direct click' => [['yclid' => '123'], 'Яндекс Директ'],
    'referrer' => [['referrer' => 'https://google.com/search?q=x'], 'Переход с google.com'],
    'nothing' => [null, null],
]);
