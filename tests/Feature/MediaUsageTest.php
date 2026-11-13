<?php

use AdminPhp\AdminPhp;
use AdminPhp\Fields\Image;
use AdminPhp\Fields\Repeater;
use AdminPhp\Fields\Text;
use AdminPhp\Models\Media;
use AdminPhp\Models\MediaUsage;
use AdminPhp\Tests\Fixtures\SiteSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
    actingAsAdmin();
});

/**
 * An image of the media library, with its file and one smaller copy on the disk.
 */
function storedMedia(string $name = 'photo.jpg'): Media
{
    $path = 'adminphp/'.Str::random(10).'.jpg';
    $variantPath = 'adminphp/variants/'.Str::random(10).'.jpg';
    Storage::disk('public')->put($path, 'original');
    Storage::disk('public')->put($variantPath, 'variant');

    return Media::query()->create([
        'disk' => 'public',
        'path' => $path,
        'name' => $name,
        'mime_type' => 'image/jpeg',
        'size' => 8,
        'width' => 1200,
        'height' => 800,
        'variants' => ['small' => ['path' => $variantPath, 'width' => 400, 'height' => 267]],
    ]);
}

/**
 * @return array<int, string>
 */
function mediaIdsUsedBy(string $id): array
{
    return MediaUsage::query()->where('usable_type', 'article')->where('usable_id', $id)->orderBy('media_id')->pluck('media_id')->all();
}

it('records the images of an item, in its live values and its pending draft', function () {
    [$cover, $inBlock, $newCover] = [storedMedia(), storedMedia(), storedMedia()];
    $ids = [$cover->id, $inBlock->id, $newCover->id];
    sort($ids);

    $id = createArticleItem([
        'cover' => $cover->id,
        'content' => [['type' => 'image', 'content' => ['image' => $inBlock->id]]],
    ]);
    publishArticleItem($id);

    $this->putJson("/admin/api/modules/article/items/{$id}", ['cover' => $newCover->id])->assertOk();

    expect(mediaIdsUsedBy($id))->toBe($ids);

    publishArticleItem($id);

    expect(mediaIdsUsedBy($id))->toBe(collect([$inBlock->id, $newCover->id])->sort()->values()->all());
});

it('forgets the images of a discarded draft', function () {
    [$cover, $draftCover] = [storedMedia(), storedMedia()];
    $id = createArticleItem(['cover' => $cover->id]);
    publishArticleItem($id);
    $this->putJson("/admin/api/modules/article/items/{$id}", ['cover' => $draftCover->id])->assertOk();

    $this->deleteJson("/admin/api/modules/article/items/{$id}/draft")->assertOk();

    expect(mediaIdsUsedBy($id))->toBe([$cover->id]);
});

it('records the images of the settings', function () {
    app(AdminPhp::class)->registerSettings(SiteSettings::class);
    $logo = storedMedia('logo.png');

    $this->putJson('/admin/api/settings', ['site_name' => 'Ferme', 'logo' => $logo->id])->assertOk();

    $this->getJson("/admin/api/medias/{$logo->id}")
        ->assertOk()
        ->assertJsonPath('data.usageCount', 1)
        ->assertJsonPath('data.usages.0.type', 'settings')
        ->assertJsonPath('data.usages.0.id', null);
});

it('collects the images of the items of a repeater', function () {
    $repeater = Repeater::make('speakers')->fields([Text::make('name'), Image::make('photo')]);

    expect($repeater->mediaIds([['name' => 'Ana', 'photo' => 'a'], ['name' => 'Léo', 'photo' => null], ['photo' => 'b']]))->toBe(['a', 'b']);
});

it('lists the contents using an image, trashed ones included', function () {
    $cover = storedMedia();
    $live = createArticleItem(['title' => ['fr' => 'Grange'], 'cover' => $cover->id]);
    $trashed = createArticleItem(['title' => ['fr' => 'Étable'], 'cover' => $cover->id]);
    $this->deleteJson("/admin/api/modules/article/items/{$trashed}")->assertNoContent();

    $usages = $this->getJson("/admin/api/medias/{$cover->id}")->assertOk()->assertJsonPath('data.usageCount', 2)->json('data.usages');

    expect(collect($usages)->mapWithKeys(fn (array $usage): array => [$usage['id'] => [$usage['title'], $usage['isTrashed']]])->all())
        ->toBe([$live => ['Grange', false], $trashed => ['Étable', true]]);

    $this->getJson('/admin/api/medias')->assertJsonPath('data.0.usageCount', 2);
});

it('refuses to delete an image a content still uses', function () {
    $cover = storedMedia();
    createArticleItem(['cover' => $cover->id]);

    $this->deleteJson("/admin/api/medias/{$cover->id}")
        ->assertConflict()
        ->assertJsonPath('message', 'Cette image est utilisée par 1 contenu : retirez-la d’abord.');

    expect(Media::query()->find($cover->id))->not->toBeNull();
    Storage::disk('public')->assertExists($cover->path);
});

it('deletes an unused image with its files', function () {
    $cover = storedMedia();
    $id = createArticleItem(['cover' => $cover->id]);
    $this->putJson("/admin/api/modules/article/items/{$id}", ['cover' => null])->assertOk();

    $this->deleteJson("/admin/api/medias/{$cover->id}")->assertNoContent();

    expect(Media::query()->find($cover->id))->toBeNull();
    Storage::disk('public')->assertMissing($cover->path);
    Storage::disk('public')->assertMissing($cover->variants['small']['path']);
});

it('frees the images of an item deleted for good', function () {
    $cover = storedMedia();
    $id = createArticleItem(['cover' => $cover->id]);
    $this->deleteJson("/admin/api/modules/article/items/{$id}")->assertNoContent();
    $this->postJson('/admin/api/modules/article/items/bulk', ['action' => 'delete', 'ids' => [$id]])->assertOk();

    $this->deleteJson("/admin/api/medias/{$cover->id}")->assertNoContent();
});

it('records the usages of existing contents again with a command', function () {
    $cover = storedMedia();
    $id = createArticleItem(['cover' => $cover->id]);
    MediaUsage::query()->delete();

    $this->artisan('adminphp:media:sync-usages')->assertSuccessful();

    expect(mediaIdsUsedBy($id))->toBe([$cover->id]);
});
