<?php

namespace App\Models;

use App\Services\Settings;
use Database\Factories\UserFactory;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['branch_id', 'name', 'username', 'email', 'phone', 'password', 'pin', 'locale', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'pin', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    use HasRoles {
        checkPermissionTo as roleCheckPermissionTo;
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
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The branch this user works in. Null means the user can access all branches (owner).
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Whether this user can see and switch between all branches.
     */
    /**
     * Permissions of a switched-off module are denied for everyone (e.g. repairs when the shop does not repair).
     * This is what @can, $user->can() and the can: route middleware use.
     */
    public function checkPermissionTo($permission, ?string $guardName = null): bool
    {
        if ($permission === 'repairs.manage' && ! app(Settings::class)->bool('repairs_enabled')) {
            return false;
        }

        return $this->roleCheckPermissionTo($permission, $guardName);
    }

    public function canAccessAllBranches(): bool
    {
        return $this->branch_id === null || $this->hasRole('admin');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'username', 'email', 'phone', 'branch_id', 'locale', 'is_active'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
