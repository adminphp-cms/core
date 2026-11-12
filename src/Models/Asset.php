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
 * @property array<string, array{path: string, width: int, height: int}>|null $variants Smaller copies by size name.
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
        'variants',
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
            'variants' => 'array',
        ];
    }

    /**
     * Public address of the file, to use in an <img> on the site. With a size name from
     * adminphp.media.sizes, the smaller copy; the original until it exists, or when the
     * image is smaller than that size.
     */
    public function url(?string $size = null): string
    {
        $path = $size === null ? $this->path : ($this->variants[$size]['path'] ?? $this->path);

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * The smallest size of adminphp.media.sizes, used for thumbnails in the admin.
     */
    protected function smallestSizeName(): ?string
    {
        $sizes = (array) config('adminphp.media.sizes', []);
        asort($sizes);

        return array_key_first($sizes);
    }

    /**
     * Every available width of the image, smaller copies then the original, for <img srcset>.
     */
    public function srcset(): string
    {
        $candidates = collect($this->variants ?? [])
            ->map(fn (array $variant): string => Storage::disk($this->disk)->url($variant['path']).' '.$variant['width'].'w')
            ->values();

        if ($this->width !== null) {
            $candidates->push($this->url().' '.$this->width.'w');
        }

        return $candidates->implode(', ');
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
     * @return array{id: string, url: string, thumbnailUrl: string, name: string, mimeType: string, size: int, width: int|null, height: int|null, createdAt: string}
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'thumbnailUrl' => $this->url($this->smallestSizeName()),
            'name' => $this->name,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
