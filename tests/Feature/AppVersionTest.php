<?php

use App\Models\User;

// Версия сайта внизу админки: какой коммит и из какой ветки выложен, и ссылка сравнить с GitHub

beforeEach(function () {
    $this->versionFile = tempnam(sys_get_temp_dir(), 'version');
    config(['app.version_file' => $this->versionFile, 'app.repository_url' => 'https://github.com/itwawox/car-on-time']);
});

afterEach(fn () => @unlink($this->versionFile));

it('shows deployed commit, branch and time with a link to compare with GitHub', function () {
    file_put_contents($this->versionFile, json_encode([
        'commit' => '7c6caea1b2c3d4e5f60718293a4b5c6d7e8f9012',
        'branch' => 'dev',
        'deployed_at' => '2026-10-15T15:04:00Z',
    ]));

    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Версия 7c6caea')
        ->assertSee('ветка dev')
        ->assertSee('15.10.2026 18:04')
        ->assertSee('https://github.com/itwawox/car-on-time/compare/7c6caea1b2c3d4e5f60718293a4b5c6d7e8f9012...dev', false);
});

it('says honestly when the version was not recorded', function () {
    unlink($this->versionFile);

    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Версия не записана');
});

it('does not show the version to guests', function () {
    $this->get('/admin/login')->assertOk()->assertDontSee('Версия');
});
