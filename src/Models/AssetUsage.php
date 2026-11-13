<?php

namespace Gmarineau\AdminPhp\Models;

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An asset shown by a content: an item of a module (its live values or its pending draft)
 * or the settings. Kept up to date on every save, so the media library knows what an image
 * is used by before deleting it.
 *
 * @property string $id
 * @property string $asset_id
 * @property string $usable_type A module name, or "settings".
 * @property string $usable_id The id of the item, or "settings".
 */
class AssetUsage extends Model
{
    use HasUlids;

    public const SETTINGS = 'settings';

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'usable_type',
        'usable_id',
    ];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('asset_usages');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
