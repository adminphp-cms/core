<?php

use Gmarineau\AdminPhp\Fields\Image;
use Gmarineau\AdminPhp\Models\Asset;
use Gmarineau\AdminPhp\Models\Block;
use Gmarineau\AdminPhp\Tests\Fixtures\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = actingAsAdmin();
});

function uploadImage(UploadedFile $file): TestResponse
{
    return test()->post('/admin/api/assets', ['file' => $file], ['Accept' => 'application/json']);
}

it('stores an uploaded image with a random name and remembers its details', function () {
    $response = uploadImage(UploadedFile::fake()->image('Façade de la ferme.jpg', 1200, 800))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Façade de la ferme.jpg')
        ->assertJsonPath('data.width', 1200)
        ->assertJsonPath('data.height', 800)
        ->assertJsonPath('data.mimeType', 'image/jpeg');

    $asset = Asset::query()->findOrFail($response->json('data.id'));

    Storage::disk('public')->assertExists($asset->path);

    expect($asset->path)->toStartWith('adminphp/')
        ->not->toContain('Façade')
        ->and($asset->user_id)->toBe($this->admin->id)
        ->and($response->json('data.url'))->toEndWith($asset->path);
});

it('refuses files that are not accepted images', function (UploadedFile $file) {
    uploadImage($file)->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(Asset::query()->count())->toBe(0);
})->with([
    'SVG, which can carry scripts' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'PDF' => fn () => UploadedFile::fake()->create('devis.pdf', 100, 'application/pdf'),
    'too large' => fn () => UploadedFile::fake()->image('huge.jpg')->size(20000),
]);

it('lists the newest images first and searches them by name', function () {
    uploadImage(UploadedFile::fake()->image('jardin.png'));
    $this->travel(1)->minute();
    uploadImage(UploadedFile::fake()->image('charpente.png'));

    $this->getJson('/admin/api/assets')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'charpente.png')
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/admin/api/assets?search=JARD')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'jardin.png');
});

it('shows one image and answers 404 for an unknown one', function () {
    $id = uploadImage(UploadedFile::fake()->image('jardin.png'))->json('data.id');

    $this->getJson("/admin/api/assets/{$id}")->assertOk()->assertJsonPath('data.id', $id);
    $this->getJson('/admin/api/assets/'.Str::ulid())->assertNotFound();
});

it('keeps the media library for logged in admins only', function () {
    auth('adminphp')->logout();

    $this->getJson('/admin/api/assets')->assertUnauthorized();
    uploadImage(UploadedFile::fake()->image('jardin.png'))->assertUnauthorized();
});

it('saves an image block and gives the site its asset', function () {
    $assetId = uploadImage(UploadedFile::fake()->image('jardin.png'))->json('data.id');

    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Un jardin'],
        'content' => [['type' => 'image', 'content' => ['image' => $assetId, 'alt' => ['fr' => 'Un massif fleuri']]]],
    ])->assertCreated()->assertJsonPath('data.values.content.0.content.image', $assetId);

    $block = Article::query()->sole()->blocks()->sole();

    expect($block->asset('image')?->id)->toBe($assetId)
        ->and($block->translated('alt'))->toBe('Un massif fleuri')
        ->and($block->asset('alt'))->toBeNull();
});

it('refuses an image block pointing to an unknown asset', function () {
    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Un jardin'],
        'content' => [['type' => 'image', 'content' => ['image' => (string) Str::ulid()]]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['content.0.content.image']);
});

it('cannot make an image translatable', function () {
    Image::make('image')->translatable();
})->throws(LogicException::class);

it('returns no asset for an empty image field', function () {
    expect((new Block(['content' => ['image' => null]]))->asset('image'))->toBeNull();
});
