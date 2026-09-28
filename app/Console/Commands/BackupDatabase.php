<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Support\Backups;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database
        {--keep=14 : Сколько последних копий хранить}
        {--connection= : Подключение из config/database.php (по умолчанию — основное)}
        {--source=console : Кто запустил: schedule, deploy, manual, console — для журнала}
        {--user= : Сотрудник, запустивший копию из админки}';

    protected $description = 'Резервная копия базы данных в storage/backups (перед каждым обновлением сайта и раз в сутки)';

    public function handle(): int
    {
        $dir = Backups::directory();
        File::ensureDirectoryExists($dir);
        $connection = config('database.connections.'.($this->option('connection') ?: config('database.default')));
        $sqlite = ($connection['driver'] ?? null) === 'sqlite';
        $target = $dir.'/db-'.now()->format('Y-m-d_H-i-s').($sqlite ? '.sqlite' : '.sql.gz');
        $startedAt = microtime(true);
        $run = $this->startRun();

        $error = $sqlite ? $this->copySqlite($connection, $target) : $this->dumpMysql($connection, $target);
        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        if ($error !== null) {
            File::delete($target);
            $run?->update(['status' => 'failed', 'error' => $error, 'duration_ms' => $duration]);
            $this->error('Не удалось сделать копию: '.$error);

            return self::FAILURE;
        }

        $size = (int) File::size($target);
        $run?->update(['status' => 'success', 'file' => basename($target), 'size' => $size, 'duration_ms' => $duration]);
        $this->prune($dir, max(1, (int) $this->option('keep')));
        $this->info('Копия базы: '.$target.' ('.Backups::humanSize($size).')');

        return self::SUCCESS;
    }

    /** Журнала может ещё не быть: первая выкладка делает копию до миграций. */
    private function startRun(): ?BackupRun
    {
        if (! Schema::hasTable('backup_runs')) {
            return null;
        }

        $source = (string) $this->option('source');

        return BackupRun::query()->create([
            'source' => array_key_exists($source, BackupRun::SOURCES) ? $source : 'console',
            'status' => 'failed',
            'user_id' => $this->option('user') ?: null,
            'started_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $connection */
    private function copySqlite(array $connection, string $target): ?string
    {
        $database = (string) $connection['database'];
        if (! File::isFile($database)) {
            return 'файл базы не найден: '.$database;
        }

        return File::copy($database, $target) ? null : 'не удалось скопировать файл базы';
    }

    /** @param  array<string, mixed>  $connection */
    private function dumpMysql(array $connection, string $target): ?string
    {
        $options = ['--single-transaction', '--quick', '--no-tablespaces', '--default-character-set=utf8mb4'];
        if ($this->supportsColumnStatistics()) {
            $options[] = '--column-statistics=0';
        }
        $dump = implode(' ', array_map('escapeshellarg', [
            'mysqldump', ...$options,
            '-h', (string) $connection['host'], '-P', (string) $connection['port'], '-u', (string) $connection['username'],
            (string) $connection['database'],
        ]));

        // Пароль — через переменную окружения, чтобы он не светился в списке процессов
        $result = Process::env(['MYSQL_PWD' => (string) $connection['password']])
            ->timeout(600)
            ->run('set -o pipefail; '.$dump.' | gzip > '.escapeshellarg($target));

        return $result->successful() ? null : (trim($result->errorOutput()) ?: 'mysqldump завершился с ошибкой');
    }

    /**
     * mysqldump 8 по умолчанию запрашивает COLUMN_STATISTICS, которой нет в MySQL 5.7 и Percona на хостинге,
     * и падает. У MariaDB такой опции нет вовсе — поэтому выключаем, только если клиент её знает.
     */
    private function supportsColumnStatistics(): bool
    {
        return str_contains(Process::run('mysqldump --help')->output(), 'column-statistics');
    }

    private function prune(string $dir, int $keep): void
    {
        collect(File::files($dir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'db-'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->slice($keep)
            ->each(fn ($file) => File::delete($file->getPathname()));
    }
}
