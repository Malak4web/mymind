<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Role name granting unrestricted access.
     *
     * TODO: replace with a `view-all-projects` permission so authorization
     * no longer depends on a human-editable role name.
     */
    public const ADMIN_ROLE = 'مدير';

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    public function dailyTasks(): HasMany
    {
        return $this->hasMany(DailyTask::class);
    }

    public function projectCategories(): HasMany
    {
        return $this->hasMany(ProjectCategory::class);
    }

    public function dailyNotes(): HasMany
    {
        return $this->hasMany(DailyNote::class);
    }

    public function isAdmin(): bool
    {
        return $this->role !== null && $this->role->name === self::ADMIN_ROLE;
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if (! $this->role) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return $this->role->permissions()->where('slug', $permissionSlug)->exists();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
