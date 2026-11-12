<?php

namespace Gmarineau\AdminPhp\Models;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Database\Factories\UserFactory;
use Gmarineau\AdminPhp\Enums\UserRole;
use Gmarineau\AdminPhp\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A person who can log in to the admin, distinct from the users of the site.
 *
 * An invited user has no password until they choose one from the invitation
 * link; a deactivated user can no longer log in but stays the author of
 * what they did.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property UserRole $role
 * @property Carbon|null $last_login_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $created_at
 */
class User extends Model implements AuthenticatableContract, CanResetPassword
{
    use Authenticatable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUlids;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'editor',
    ];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('users');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function hasPendingInvitation(): bool
    {
        return $this->password === null;
    }

    public function deactivate(): void
    {
        // A new remember token invalidates the "stay logged in" cookies already handed out.
        $this->forceFill(['deactivated_at' => now()])->setRememberToken(Str::random(60));
        $this->save();
    }

    public function activate(): void
    {
        $this->forceFill(['deactivated_at' => null])->save();
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * The logged in user, as the admin front knows it.
     *
     * @return array{id: string, name: string, email: string, role: string, canManageUsers: bool, canManageSettings: bool}
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'canManageUsers' => $this->isAdmin(),
            'canManageSettings' => $this->isAdmin(),
        ];
    }

    /**
     * A row of the users page.
     *
     * @return array{id: string, name: string, email: string, role: string, roleLabel: string, status: 'active'|'invited'|'deactivated', lastLoginAt: string|null, createdAt: string|null}
     */
    public function toManagementArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'roleLabel' => $this->role->label(),
            'status' => match (true) {
                ! $this->isActive() => 'deactivated',
                $this->hasPendingInvitation() => 'invited',
                default => 'active',
            },
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
