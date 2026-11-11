<?php

namespace Gmarineau\AdminPhp\Models;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person who can log in to the admin, distinct from the users of the site.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $last_login_at
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
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
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return array{id: string, name: string, email: string}
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
