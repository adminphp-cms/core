<?php

namespace AdminPhp\Models;

use AdminPhp\AdminPhp;
use AdminPhp\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot of a content in the shape of its module form (fields and blocks).
 *
 * @property string $id
 * @property string $revisionable_type
 * @property string $revisionable_id
 * @property RevisionStatus $status
 * @property array<string, mixed> $payload
 * @property string|null $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Revision extends Model
{
    use HasUlids;

    protected $fillable = [
        'status',
        'payload',
        'user_id',
    ];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('revisions');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RevisionStatus::class,
            'payload' => 'array',
        ];
    }

    /**
     * Admin user who saved the draft or published the version.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }
}
