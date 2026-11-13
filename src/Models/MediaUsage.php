<?php

namespace Gmarineau\AdminPhp\Models;

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A media shown by a content: an item of a module (its live values or its pending draft)
 * or the settings. Kept up to date on every save, so the media library knows what an image
 * is used by before deleting it.
 *
 * @property string $id
 * @property string $media_id
 * @property string $usable_type A module name, or "settings".
 * @property string $usable_id The id of the item, or "settings".
 */
class MediaUsage extends Model
{
    use HasUlids;

    public const SETTINGS = 'settings';

    public $timestamps = false;

    protected $fillable = [
        'media_id',
        'usable_type',
        'usable_id',
    ];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('media_usages');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
