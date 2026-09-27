<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest
|--------------------------------------------------------------------------
| Новые тесты пишем на Pest (функции test()/it()). Старые тесты — классы PHPUnit в tests/Feature —
| Pest запускает как есть, переписывать их не нужно.
| Каждый Pest-тест в tests/Feature получает TestCase (сброс настроек сайта) и чистую базу.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
