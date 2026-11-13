<?php

namespace Gmarineau\AdminPhp\Models;

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The admin user editing an item, renewed while their editor is open.
 *
 * @property string $id
 * @property string $lockable_type
 * @property string $lockable_id
 * @property string $user_id
 * @property Carbon $locked_at
 * @property Carbon $renewed_at
 */
class Lock extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['user_id', 'locked_at', 'renewed_at'];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('locks');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'renewed_at' => 'datetime',
        ];
    }

    /**
     * Whether the lock was renewed recently enough to still hold.
     */
    public function isActive(): bool
    {
        return $this->renewed_at->gt(now()->subSeconds((int) config('adminphp.locks.ttl', 120)));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
