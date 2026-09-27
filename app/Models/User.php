<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'owner' => 'Владелец — всё, включая интеграции и сотрудников',
        'manager' => 'Менеджер — заявки, календарь, парк, отзывы',
        'editor' => 'Контент-редактор — тексты, статьи, SEO, акции',
    ];

    /**
     * Области админки и роли, которым они открыты.
     *
     * @var array<string, list<string>>
     */
    public const AREAS = [
        'bookings' => ['owner', 'manager'],
        'fleet' => ['owner', 'manager'],
        'reviews' => ['owner', 'manager', 'editor'],
        'content' => ['owner', 'editor'],
        'admin' => ['owner'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && array_key_exists((string) $this->role, self::ROLES);
    }

    public function canUseArea(string $area): bool
    {
        return $this->is_active && in_array($this->role, self::AREAS[$area] ?? [], true);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
