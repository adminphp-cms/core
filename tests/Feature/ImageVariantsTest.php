<?php

use Gmarineau\AdminPhp\Actions\GenerateAssetVariants;
use Gmarineau\AdminPhp\Jobs\GenerateAssetVariantsJob;
use Gmarineau\AdminPhp\Models\Asset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['adminphp.media.sizes' => ['md' => 960, 'sm' => 480, 'xl' => 2400]]);
    actingAsAdmin();
});

function uploadedAsset(int $width, int $height, string $name = 'photo.jpg'): Asset
{
    $id = test()->post('/admin/api/assets', ['file' => UploadedFile::fake()->image($name, $width, $height)], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.id');

    return Asset::query()->findOrFail($id);
}

it('queues the smaller copies when an image is uploaded', function () {
    Queue::fake();

    $asset = uploadedAsset(1200, 800);

    Queue::assertPushed(GenerateAssetVariantsJob::class, fn (GenerateAssetVariantsJob $job): bool => $job->asset->is($asset));
});

it('makes webp copies of every size smaller than the original, never larger', function () {
    $asset = uploadedAsset(1200, 800);

    expect(array_keys($asset->variants))->toBe(['sm', 'md'])
        ->and($asset->variants['sm'])->toMatchArray(['width' => 480, 'height' => 320])
        ->and($asset->variants['md'])->toMatchArray(['width' => 960, 'height' => 640])
        ->and($asset->variants['md']['path'])->toBe("adminphp/variants/{$asset->id}/md.webp");

    Storage::disk('public')->assertExists($asset->variants['md']['path']);
    expect(getimagesizefromstring(Storage::disk('public')->get($asset->variants['md']['path']))['mime'])->toBe('image/webp');
});

it('gives the url of a size, falling back to the original', function () {
    $asset = uploadedAsset(1200, 800);

    expect($asset->url('md'))->toEndWith("variants/{$asset->id}/md.webp")
        ->and($asset->url('xl'))->toBe($asset->url())
        ->and($asset->url())->toEndWith($asset->path)
        ->and($asset->toAdminArray()['thumbnailUrl'])->toEndWith('sm.webp');
});

it('lists every width for srcset, the original last', function () {
    $asset = uploadedAsset(1200, 800);

    expect($asset->srcset())->toBe(sprintf('%s 480w, %s 960w, %s 1200w', $asset->url('sm'), $asset->url('md'), $asset->url()));
});

it('keeps only the original of a gif or an image smaller than every size', function () {
    expect(uploadedAsset(300, 200)->variants)->toBeNull()
        ->and(uploadedAsset(1200, 800, 'anim.gif')->variants)->toBeNull();
});

it('replaces the copies and removes the stale ones when sizes change', function () {
    $asset = uploadedAsset(1200, 800);
    $oldSmallPath = $asset->variants['sm']['path'];

    config(['adminphp.media.sizes' => ['md' => 960]]);
    app(GenerateAssetVariants::class)->handle($asset);

    expect(array_keys($asset->fresh()->variants))->toBe(['md']);
    Storage::disk('public')->assertMissing($oldSmallPath);
});

it('regenerates the copies from the command', function () {
    Queue::fake();
    $asset = uploadedAsset(1200, 800);

    expect($asset->fresh()->variants)->toBeNull();

    $this->artisan('adminphp:media:regenerate')->assertSuccessful();

    expect(array_keys($asset->fresh()->variants))->toBe(['sm', 'md']);
});

it('renders a responsive image with the blade component', function () {
    $asset = uploadedAsset(1200, 800);

    $html = Blade::render('<x-adminphp::image :asset="$asset" alt="Ferme" sizes="50vw" class="rounded" />', ['asset' => $asset]);

    expect($html)->toContain('src="'.$asset->url().'"')
        ->toContain('srcset="'.$asset->srcset().'"')
        ->toContain('sizes="50vw"')
        ->toContain('width="1200" height="800"')
        ->toContain('alt="Ferme"')
        ->toContain('class="rounded"');
});

it('renders nothing for a missing image', function () {
    expect(trim(Blade::render('<x-adminphp::image :asset="null" />')))->toBe('');
});
