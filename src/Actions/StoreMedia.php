<?php

namespace AdminPhp\Actions;

use AdminPhp\AdminPhp;
use AdminPhp\Jobs\GenerateMediaVariantsJob;
use AdminPhp\Models\Media;
use Illuminate\Http\UploadedFile;

/**
 * Adds an uploaded image to the media library. The file gets a random name on the
 * configured disk; its original name is only kept for display. Smaller copies are
 * made by a queued job.
 */
class StoreMedia
{
    public function __construct(protected AdminPhp $adminPhp) {}

    public function handle(UploadedFile $file): Media
    {
        $disk = (string) config('adminphp.media.disk', 'public');
        $path = $file->store((string) config('adminphp.media.directory', 'adminphp'), $disk);

        [$width, $height] = $this->dimensions($file);

        $media = Media::query()->create([
            'disk' => $disk,
            'path' => $path,
            'name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
            'user_id' => $this->adminPhp->user()?->getKey(),
        ]);

        GenerateMediaVariantsJob::dispatch($media);

        return $media;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function dimensions(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        return $size === false ? [null, null] : [$size[0], $size[1]];
    }
}
