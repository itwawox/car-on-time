<?php

use App\Filament\Resources\BackupRuns\BackupRunResource;
use App\Filament\Resources\BackupRuns\Pages\ListBackupRuns;
use App\Filament\Resources\BackupRuns\Widgets\BackupStorageOverview;
use App\Filament\Widgets\SystemHealthOverview;
use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

// Раздел «Резервные копии»: журнал каждого запуска, скачивание копий, копия по кнопке

beforeEach(function () {
    config(['app.backups_path' => sys_get_temp_dir().'/car-backups-'.uniqid()]);
    File::ensureDirectoryExists(config('app.backups_path'));
    config(['database.connections.backup_mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'car', 'username' => 'car', 'password' => 'secret',
    ]]);
    $this->owner = User::factory()->create();
});

afterEach(fn () => File::deleteDirectory(config('app.backups_path')));

function fakeDump(bool $ok = true): void
{
    Process::fake([
        '*--help*' => Process::result(''),
        '*' => function (PendingProcess $process) use ($ok) {
            if (! $ok) {
                return Process::result(errorOutput: 'mysqldump: Got error: 1045: Access denied', exitCode: 2);
            }
            preg_match("/> '([^']+)'$/", (string) $process->command, $target);
            File::put($target[1], str_repeat('x', 2048));

            return Process::result();
        },
    ]);
}

it('logs every backup run with its source, file, size and duration', function () {
    fakeDump();

    $this->artisan('backup:database', ['--connection' => 'backup_mysql', '--source' => 'schedule'])->assertSuccessful();

    $run = BackupRun::query()->sole();
    expect($run)->status->toBe('success')->source->toBe('schedule')->size->toBe(2048)
        ->and($run->file)->toMatch('/^db-[0-9_-]+\.sql\.gz$/')
        ->and($run->duration_ms)->toBeInt();
});

it('logs a failed run with the error and leaves no broken file', function () {
    fakeDump(ok: false);

    $this->artisan('backup:database', ['--connection' => 'backup_mysql', '--source' => 'deploy'])->assertFailed();

    expect(BackupRun::query()->sole())->status->toBe('failed')->source->toBe('deploy')->file->toBeNull()
        ->error->toContain('Access denied')
        ->and(File::files(config('app.backups_path')))->toBeEmpty();
});

it('shows the journal to the owner, including copies made before the journal existed', function () {
    File::put(config('app.backups_path').'/db-2026-09-28_03-30-05.sql.gz', 'dump');

    $this->actingAs($this->owner)->get(BackupRunResource::getUrl())->assertOk()->assertSee('Сделать копию сейчас');
    Livewire::test(ListBackupRuns::class)->assertSee('Найдена на диске')->assertSee('на сервере');
    Livewire::test(BackupStorageOverview::class)->assertSee('Копий на сервере')->assertSee('1 из 14')->assertSee('Следующая ночная копия');

    expect(BackupRun::query()->where('file', 'db-2026-09-28_03-30-05.sql.gz')->where('source', 'found')->exists())->toBeTrue();
});

it('is closed for managers', function () {
    $this->actingAs(User::factory()->role('manager')->create())->get(BackupRunResource::getUrl())->assertForbidden();
});

it('downloads a stored copy and never serves other files', function () {
    File::put(config('app.backups_path').'/db-2026-09-28_12-00-00.sql.gz', 'dump');
    $stored = BackupRun::query()->create(['source' => 'console', 'status' => 'success', 'file' => 'db-2026-09-28_12-00-00.sql.gz', 'size' => 4, 'started_at' => now()]);
    $pruned = BackupRun::query()->create(['source' => 'schedule', 'status' => 'success', 'file' => 'db-2026-09-01_03-30-00.sql.gz', 'size' => 4, 'started_at' => now()->subDays(27)]);
    $forged = BackupRun::query()->create(['source' => 'console', 'status' => 'success', 'file' => '../../.env', 'started_at' => now()]);

    $this->actingAs($this->owner);
    Livewire::test(ListBackupRuns::class)
        ->assertTableActionVisible('download', $stored)
        ->callTableAction('download', $stored)
        ->assertFileDownloaded('db-2026-09-28_12-00-00.sql.gz')
        ->assertTableActionHidden('download', $pruned)
        ->assertTableActionHidden('download', $forged);
});

it('makes a backup on demand and records who asked for it', function () {
    $this->actingAs($this->owner);

    Livewire::test(ListBackupRuns::class)->callAction('backupNow');

    expect(BackupRun::query()->where('source', 'manual')->sole())->user_id->toBe($this->owner->id);
});

it('opens the journal from the dashboard cards', function () {
    $this->actingAs($this->owner);

    Livewire::test(SystemHealthOverview::class)->assertSee(BackupRunResource::getUrl(), false);
});
