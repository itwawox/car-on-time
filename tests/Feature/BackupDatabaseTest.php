<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// mysqldump 8 с сервером MySQL 5.7/Percona на хостинге падает на запросе к COLUMN_STATISTICS — отключаем его, если клиент это умеет

beforeEach(function () {
    config([
        'database.connections.backup_mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
            'database' => 'car', 'username' => 'car', 'password' => 'secret',
        ],
    ]);
    File::deleteDirectory(storage_path('backups'));
});

afterEach(fn () => File::deleteDirectory(storage_path('backups')));

function fakeMysqldump(string $help): void
{
    Process::fake([
        '*--help*' => Process::result($help),
        '*' => function (PendingProcess $process) {
            preg_match("/> '([^']+)'$/", (string) $process->command, $target);
            File::put($target[1], 'dump');

            return Process::result();
        },
    ]);
}

it('turns off column statistics when mysqldump supports it', function () {
    fakeMysqldump("--column-statistics  Add an ANALYZE TABLE statement\n");

    $this->artisan('backup:database', ['--connection' => 'backup_mysql'])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => str_contains((string) $process->command, "'mysqldump'")
        && str_contains((string) $process->command, "'--column-statistics=0'"));
    expect(File::files(storage_path('backups')))->toHaveCount(1);
});

it('does not pass the option to mysqldump that does not know it', function () {
    fakeMysqldump("mysqldump  Ver 10.19 Distrib 10.6.16-MariaDB\n");

    $this->artisan('backup:database', ['--connection' => 'backup_mysql'])->assertSuccessful();

    Process::assertDidntRun(fn (PendingProcess $process) => str_contains((string) $process->command, 'column-statistics=0'));
});
