<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

/**
 * A login account. Passwordless: identity is proven by WhatsApp OTP to the
 * verified phone. Authorisation is permission-based; role names are only
 * labels for bundles of permissions.
 */
class User extends Authenticatable
{
    protected $guarded = ['id', 'remember_token'];

    protected $hidden = ['remember_token'];

    private ?Collection $permissionCache = null;

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('assigned_by', 'assigned_at', 'expires_on');
    }

    /** Roles whose (optional) expiry date has not passed. */
    public function activeRoles(): Collection
    {
        return $this->roles->filter(fn (Role $r) => ! $r->pivot->expires_on || now()->startOfDay()->lte($r->pivot->expires_on));
    }

    public function permissionKeys(): Collection
    {
        if ($this->permissionCache === null) {
            $roles = $this->activeRoles();
            $this->permissionCache = $roles->contains('key', 'super_admin')
                ? Permission::pluck('key')
                : $roles->load('permissions')->flatMap(fn (Role $r) => $r->permissions->pluck('key'))->unique()->values();
        }

        return $this->permissionCache;
    }

    public function hasPermission(string $key): bool
    {
        return $this->isUsable() && $this->permissionKeys()->contains($key);
    }

    public function hasAnyPermission(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if ($this->hasPermission($key)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $key): bool
    {
        return $this->activeRoles()->contains('key', $key);
    }

    public function isLeader(): bool
    {
        return $this->activeRoles()->contains(fn (Role $r) => $r->key !== 'member');
    }

    /** Verified phone, enabled account, member in good standing for access. */
    public function isUsable(): bool
    {
        return $this->status === 'active'
            && $this->phone_verified_at !== null
            && in_array($this->member?->membership_status, ['active', 'suspended'], true);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    public function displayName(): string
    {
        return $this->member?->displayName() ?? $this->phone_e164;
    }
}
