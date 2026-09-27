<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeOwner extends Command
{
    protected $signature = 'user:owner {email : Email для входа в админку} {--name= : Имя}';

    protected $description = 'Создать владельца админки или сделать владельцем существующего пользователя';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->update(['role' => 'owner', 'is_active' => true]);
            $this->info('Пользователь '.$email.' теперь владелец. Пароль не менялся.');

            return self::SUCCESS;
        }

        $password = Str::password(16, symbols: false);
        User::query()->create([
            'name' => $this->option('name') ?: Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->info('Владелец создан: '.$email);
        $this->warn('Пароль (покажется один раз, сохраните и смените после входа): '.$password);

        return self::SUCCESS;
    }
}
