<?php

namespace AdminPhp\Actions;

use AdminPhp\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

/**
 * Makes the smaller copies of an image declared in adminphp.media.sizes, on the
 * media's own disk, and replaces the ones made before. Never enlarges an image.
 * When an image cannot be read, it keeps its original only and the site uses it.
 */
class GenerateMediaVariants
{
    public function handle(Media $media): Media
    {
        $disk = Storage::disk($media->disk);
        $previousPaths = array_column($media->variants ?? [], 'path');
        $variants = [];

        if ($this->canResize($media)) {
            $workDirectory = (new TemporaryDirectory)->create();

            try {
                $source = $workDirectory->path('source.'.pathinfo($media->path, PATHINFO_EXTENSION));
                file_put_contents($source, $disk->get($media->path));

                $variants = $this->resize($media, $source, $workDirectory);
            } catch (Throwable $exception) {
                Log::warning("AdminPhp could not resize media [{$media->id}]: {$exception->getMessage()}");
                $variants = [];
            } finally {
                $workDirectory->delete();
            }
        }

        $stalePaths = array_diff($previousPaths, array_column($variants, 'path'));

        if ($stalePaths !== []) {
            $disk->delete(array_values($stalePaths));
        }

        $media->forceFill(['variants' => $variants === [] ? null : $variants])->save();

        return $media;
    }

    /**
     * @return array<string, array{path: string, width: int, height: int}>
     */
    protected function resize(Media $media, string $source, TemporaryDirectory $workDirectory): array
    {
        $disk = Storage::disk($media->disk);
        $format = (string) config('adminphp.media.variant_format', 'webp');
        $variants = [];

        foreach ($this->sizes() as $name => $width) {
            if ($media->width !== null && $width >= $media->width) {
                continue;
            }

            $target = $workDirectory->path("{$name}.{$format}");

            Image::useImageDriver((string) config('adminphp.media.driver', 'gd'))
                ->loadFile($source)
                ->orientation()
                ->width($width)
                ->format($format)
                ->quality((int) config('adminphp.media.variant_quality', 80))
                ->save($target);

            [$variantWidth, $variantHeight] = getimagesize($target) ?: [$width, null];
            $path = $this->variantPath($media, $name, $format);

            $disk->put($path, (string) file_get_contents($target));

            $variants[$name] = ['path' => $path, 'width' => (int) $variantWidth, 'height' => (int) $variantHeight];
        }

        return $variants;
    }

    protected function canResize(Media $media): bool
    {
        return str_starts_with($media->mime_type, 'image/') && $media->mime_type !== 'image/gif' && $media->width !== null;
    }

    /**
     * @return array<string, int>
     */
    protected function sizes(): array
    {
        $sizes = array_map(intval(...), (array) config('adminphp.media.sizes', []));
        asort($sizes);

        return $sizes;
    }

    /**
     * Next to the original: adminphp/variants/{media id}/md.webp
     */
    protected function variantPath(Media $media, string $name, string $format): string
    {
        $directory = trim((string) config('adminphp.media.directory', 'adminphp'), '/');

        return "{$directory}/variants/{$media->id}/{$name}.{$format}";
    }
}
