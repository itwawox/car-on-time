<?php

use App\Filament\Widgets\SystemHealthOverview;
use App\Models\User;
use App\Support\SystemHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

// Сводка владельца: делаются ли копии базы и жив ли планировщик (CRON) — без захода на сервер по SSH

beforeEach(function () {
    File::deleteDirectory(storage_path('backups'));
    File::ensureDirectoryExists(storage_path('backups'));
    $this->actingAs(User::factory()->create());
});

afterEach(fn () => File::deleteDirectory(storage_path('backups')));

function backupFile(string $name, int $bytes, DateTimeInterface $at): void
{
    $path = storage_path('backups/'.$name);
    File::put($path, str_repeat('x', $bytes));
    touch($path, $at->getTimestamp());
}

it('shows the latest database backup and warns when it is stale', function () {
    backupFile('db-old.sql.gz', 100, now()->subDays(3));
    backupFile('db-fresh.sql.gz', 150 * 1024, now()->subHours(2));

    expect(SystemHealth::lastBackup())->toMatchArray(['name' => 'db-fresh.sql.gz', 'size' => 150 * 1024])
        ->and(SystemHealth::backupIsFresh())->toBeTrue();
    Livewire::test(SystemHealthOverview::class)->assertSee('Последняя копия базы')->assertSee('150 КБ');

    $this->travel(2)->days();

    expect(SystemHealth::backupIsFresh())->toBeFalse();
    Livewire::test(SystemHealthOverview::class)->assertSee('Копия устарела');
});

it('says when there are no backups at all', function () {
    expect(SystemHealth::lastBackup())->toBeNull();
    Livewire::test(SystemHealthOverview::class)->assertSee('Копий нет');
});

it('tracks that the scheduler runs every minute', function () {
    expect(SystemHealth::schedulerLastRun())->toBeNull();
    Livewire::test(SystemHealthOverview::class)->assertSee('ни разу');

    Artisan::call('schedule:test', ['--name' => 'scheduler:heartbeat']);

    expect(SystemHealth::schedulerIsAlive())->toBeTrue();
    Livewire::test(SystemHealthOverview::class)->assertSee('Планировщик (CRON)')->assertDontSee('ни разу');

    $this->travel(10)->minutes();

    expect(SystemHealth::schedulerIsAlive())->toBeFalse();
    Livewire::test(SystemHealthOverview::class)->assertSee('CRON не запускается');
});

it('is shown to the owner only', function () {
    expect(SystemHealthOverview::canView())->toBeTrue();

    $this->actingAs(User::factory()->role('manager')->create());

    expect(SystemHealthOverview::canView())->toBeFalse();
});
