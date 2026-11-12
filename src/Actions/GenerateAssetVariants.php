<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\Models\Asset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

/**
 * Makes the smaller copies of an image declared in adminphp.media.sizes, on the
 * asset's own disk, and replaces the ones made before. Never enlarges an image.
 * When an image cannot be read, it keeps its original only and the site uses it.
 */
class GenerateAssetVariants
{
    public function handle(Asset $asset): Asset
    {
        $disk = Storage::disk($asset->disk);
        $previousPaths = array_column($asset->variants ?? [], 'path');
        $variants = [];

        if ($this->canResize($asset)) {
            $workDirectory = (new TemporaryDirectory)->create();

            try {
                $source = $workDirectory->path('source.'.pathinfo($asset->path, PATHINFO_EXTENSION));
                file_put_contents($source, $disk->get($asset->path));

                $variants = $this->resize($asset, $source, $workDirectory);
            } catch (Throwable $exception) {
                Log::warning("AdminPhp could not resize asset [{$asset->id}]: {$exception->getMessage()}");
                $variants = [];
            } finally {
                $workDirectory->delete();
            }
        }

        $stalePaths = array_diff($previousPaths, array_column($variants, 'path'));

        if ($stalePaths !== []) {
            $disk->delete(array_values($stalePaths));
        }

        $asset->forceFill(['variants' => $variants === [] ? null : $variants])->save();

        return $asset;
    }

    /**
     * @return array<string, array{path: string, width: int, height: int}>
     */
    protected function resize(Asset $asset, string $source, TemporaryDirectory $workDirectory): array
    {
        $disk = Storage::disk($asset->disk);
        $format = (string) config('adminphp.media.variant_format', 'webp');
        $variants = [];

        foreach ($this->sizes() as $name => $width) {
            if ($asset->width !== null && $width >= $asset->width) {
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
            $path = $this->variantPath($asset, $name, $format);

            $disk->put($path, (string) file_get_contents($target));

            $variants[$name] = ['path' => $path, 'width' => (int) $variantWidth, 'height' => (int) $variantHeight];
        }

        return $variants;
    }

    protected function canResize(Asset $asset): bool
    {
        return str_starts_with($asset->mime_type, 'image/') && $asset->mime_type !== 'image/gif' && $asset->width !== null;
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
     * Next to the original: adminphp/variants/{asset id}/md.webp
     */
    protected function variantPath(Asset $asset, string $name, string $format): string
    {
        $directory = trim((string) config('adminphp.media.directory', 'adminphp'), '/');

        return "{$directory}/variants/{$asset->id}/{$name}.{$format}";
    }
}
