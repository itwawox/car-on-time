<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=14 : Сколько последних копий хранить}';

    protected $description = 'Резервная копия базы данных в storage/backups (перед каждым обновлением сайта и раз в сутки)';

    public function handle(): int
    {
        $dir = storage_path('backups');
        File::ensureDirectoryExists($dir);
        $connection = config('database.connections.'.config('database.default'));
        $stamp = now()->format('Y-m-d_H-i-s');

        if (($connection['driver'] ?? null) === 'sqlite') {
            $target = $dir.'/db-'.$stamp.'.sqlite';
            File::copy($connection['database'], $target);
        } else {
            $target = $dir.'/db-'.$stamp.'.sql.gz';
            $dump = implode(' ', array_map('escapeshellarg', [
                'mysqldump', '--single-transaction', '--quick', '--no-tablespaces', '--default-character-set=utf8mb4',
                '-h', (string) $connection['host'], '-P', (string) $connection['port'], '-u', (string) $connection['username'],
                (string) $connection['database'],
            ]));
            // Пароль — через переменную окружения, чтобы он не светился в списке процессов
            $result = Process::env(['MYSQL_PWD' => (string) $connection['password']])
                ->timeout(600)
                ->run('set -o pipefail; '.$dump.' | gzip > '.escapeshellarg($target));

            if ($result->failed()) {
                File::delete($target);
                $this->error('Не удалось сделать копию: '.trim($result->errorOutput()));

                return self::FAILURE;
            }
        }

        $this->prune($dir, max(1, (int) $this->option('keep')));
        $this->info('Копия базы: '.$target.' ('.round(File::size($target) / 1024).' КБ)');

        return self::SUCCESS;
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
