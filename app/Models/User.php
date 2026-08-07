<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

    #[Fillable(['name', 'gender', 'date_of_birth', 'phone', 'position_id', 'username', 'email', 'password', 'status', 'preferred_locale', 'active_campus_id', 'department_id', 'photo_path', 'login_identifier', 'is_global', 'must_change_password', 'last_seen_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $casts = ['date_of_birth' => 'date:Y-m-d'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function chatConversations()
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_conversation_users', 'user_id', 'conversation_id')
            ->withPivot(['last_read_at'])->withTimestamps();
    }

    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolInfo::class, 'access_user_campuses', 'user_id', 'campus_id')
            ->withPivot(['is_primary', 'assigned_at'])
            ->withTimestamps();
    }

    public function accessibleCampuses()
    {
        if ($this->isSuperAdmin() || $this->is_global || $this->roles()->where('is_global', true)->exists()) {
            return SchoolInfo::query()->where('status', 1)->getQuery()->getModel()->newQuery()->where('status', 1);
        }

        return $this->campuses()->where('tb_school_info.status', 1);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'access_user_roles')->withPivot('campus_id');
    }

    public function permissionOverrides()
    {
        return $this->belongsToMany(Permission::class, 'access_user_permission_overrides')->withPivot('allowed')->withTimestamps();
    }

    public function activeCampus(): BelongsTo
    {
        return $this->belongsTo(SchoolInfo::class, 'active_campus_id');
    }

    public function canAccessCampus(int $campusId): bool
    {
        if ($this->isSuperAdmin() || $this->is_global || $this->roles()->where('is_global', true)->where('access_roles.status', 1)->exists()) {
            return SchoolInfo::query()->whereKey($campusId)->where('status', 1)->exists();
        }

        return $this->campuses()->whereKey($campusId)->where('tb_school_info.status', 1)->exists();
    }

    public function hasPermission(string $permission, ?int $campusId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->permissionOverrides()->where('code', $permission)->wherePivot('allowed', true)->exists()) {
            return true;
        }

        if ($this->department_id && $this->department()->whereHas('permissions', fn ($query) => $query->where('code', $permission))->exists()
            && ($this->is_global || ($campusId && $this->canAccessCampus($campusId)))) {
            return true;
        }

        return $this->roles()->where('access_roles.status', 1)
            ->whereHas('permissions', fn ($query) => $query->where('code', $permission))
            ->where(function ($query) use ($campusId) {
                $query->where('is_global', true)->orWhere('access_user_roles.campus_id', $campusId);
            })->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()
            ->where('access_roles.code', 'super-admin')
            ->where('access_roles.status', 1)
            ->exists();
    }

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
            'status' => 'integer',
            'is_global' => 'boolean',
            'must_change_password' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}
