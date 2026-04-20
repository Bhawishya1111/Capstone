<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_ANALYST = 'analyst';

    public const ROLE_USER = 'user';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAnalyst(): bool
    {
        return $this->role === self::ROLE_ANALYST;
    }

    public function canViewTechnicalReports(): bool
    {
        return $this->isAdmin() || $this->isAnalyst();
    }

    /**
     * Roles shown in management UI.
     *
     * @return array<int, string>
     */
    public static function allowedRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_ANALYST,
            self::ROLE_USER,
        ];
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * Websites this user may use for scans when not an admin (assigned via pivot).
     */
    public function assignedWebsites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class, 'website_user')->withTimestamps();
    }

    public function createdWebsites(): HasMany
    {
        return $this->hasMany(Website::class, 'created_by');
    }
}
