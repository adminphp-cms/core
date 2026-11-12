<?php

namespace Gmarineau\AdminPhp\Models;

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file of the media library, reusable by any content.
 *
 * @property string $id
 * @property string $disk
 * @property string $path
 * @property string $name Original file name, for people.
 * @property string $mime_type
 * @property int $size In bytes.
 * @property int|null $width
 * @property int|null $height
 * @property string|null $user_id
 * @property Carbon $created_at
 */
class Asset extends Model
{
    use HasUlids;

    protected $fillable = [
        'disk',
        'path',
        'name',
        'mime_type',
        'size',
        'width',
        'height',
        'user_id',
    ];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('assets');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * Public address of the file, to use in an <img> on the site.
     */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Admin user who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{id: string, url: string, name: string, mimeType: string, size: int, width: int|null, height: int|null, createdAt: string}
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'name' => $this->name,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
