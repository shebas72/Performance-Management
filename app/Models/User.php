<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasRoles, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /** Whether the user's role grants a permission (see App\Support\Permissions). */
    public function hasPermission(string $permission): bool
    {
        return \App\Services\RolePermissions::resolve()->allows($this, $permission);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'super-admin' && (bool) $this->is_super_admin;
    }

    /** Photo shown in the super-admin panel's user menu (uploaded from the panel's Profile page). */
    public function getFilamentAvatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return str_starts_with($this->avatar, 'http') ? $this->avatar : asset('storage/'.$this->avatar);
    }
}
