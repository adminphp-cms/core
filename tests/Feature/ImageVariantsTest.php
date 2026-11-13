<?php

use Gmarineau\AdminPhp\Actions\GenerateMediaVariants;
use Gmarineau\AdminPhp\Jobs\GenerateMediaVariantsJob;
use Gmarineau\AdminPhp\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['adminphp.media.sizes' => ['md' => 960, 'sm' => 480, 'xl' => 2400]]);
    actingAsAdmin();
});

function uploadedMedia(int $width, int $height, string $name = 'photo.jpg'): Media
{
    $id = test()->post('/admin/api/medias', ['file' => UploadedFile::fake()->image($name, $width, $height)], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.id');

    return Media::query()->findOrFail($id);
}

it('queues the smaller copies when an image is uploaded', function () {
    Queue::fake();

    $media = uploadedMedia(1200, 800);

    Queue::assertPushed(GenerateMediaVariantsJob::class, fn (GenerateMediaVariantsJob $job): bool => $job->media->is($media));
});

it('makes webp copies of every size smaller than the original, never larger', function () {
    $media = uploadedMedia(1200, 800);

    expect(array_keys($media->variants))->toEqualCanonicalizing(['sm', 'md'])
        ->and($media->variants['sm'])->toMatchArray(['width' => 480, 'height' => 320])
        ->and($media->variants['md'])->toMatchArray(['width' => 960, 'height' => 640])
        ->and($media->variants['md']['path'])->toBe("adminphp/variants/{$media->id}/md.webp");

    Storage::disk('public')->assertExists($media->variants['md']['path']);
    expect(getimagesizefromstring(Storage::disk('public')->get($media->variants['md']['path']))['mime'])->toBe('image/webp');
});

it('gives the url of a size, falling back to the original', function () {
    $media = uploadedMedia(1200, 800);

    expect($media->url('md'))->toEndWith("variants/{$media->id}/md.webp")
        ->and($media->url('xl'))->toBe($media->url())
        ->and($media->url())->toEndWith($media->path)
        ->and($media->toAdminArray()['thumbnailUrl'])->toEndWith('sm.webp');
});

it('lists every width for srcset, the original last', function () {
    $media = uploadedMedia(1200, 800);

    expect($media->srcset())->toBe(sprintf('%s 480w, %s 960w, %s 1200w', $media->url('sm'), $media->url('md'), $media->url()));
});

it('keeps only the original of a gif or an image smaller than every size', function () {
    expect(uploadedMedia(300, 200)->variants)->toBeNull()
        ->and(uploadedMedia(1200, 800, 'anim.gif')->variants)->toBeNull();
});

it('replaces the copies and removes the stale ones when sizes change', function () {
    $media = uploadedMedia(1200, 800);
    $oldSmallPath = $media->variants['sm']['path'];

    config(['adminphp.media.sizes' => ['md' => 960]]);
    app(GenerateMediaVariants::class)->handle($media);

    expect(array_keys($media->fresh()->variants))->toBe(['md']);
    Storage::disk('public')->assertMissing($oldSmallPath);
});

it('regenerates the copies from the command', function () {
    Queue::fake();
    $media = uploadedMedia(1200, 800);

    expect($media->fresh()->variants)->toBeNull();

    $this->artisan('adminphp:media:regenerate')->assertSuccessful();

    expect(array_keys($media->fresh()->variants))->toEqualCanonicalizing(['sm', 'md']);
});

it('renders a responsive image with the blade component', function () {
    $media = uploadedMedia(1200, 800);

    $html = Blade::render('<x-adminphp::image :media="$media" alt="Ferme" sizes="50vw" class="rounded" />', ['media' => $media]);

    expect($html)->toContain('src="'.$media->url().'"')
        ->toContain('srcset="'.$media->srcset().'"')
        ->toContain('sizes="50vw"')
        ->toContain('width="1200" height="800"')
        ->toContain('alt="Ferme"')
        ->toContain('class="rounded"');
});

it('renders nothing for a missing image', function () {
    expect(trim(Blade::render('<x-adminphp::image :media="null" />')))->toBe('');
});
