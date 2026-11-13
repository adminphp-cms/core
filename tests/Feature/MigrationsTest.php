<?php

use AdminPhp\Models\Media;
use AdminPhp\Models\MediaUsage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('keeps every index name under the 64 character limit of MySQL', function (string $table) {
    $tooLongNames = collect(Schema::getIndexes($table))
        ->pluck('name')
        ->filter(fn (string $name): bool => strlen($name) > 64)
        ->all();

    expect($tooLongNames)->toBe([]);
})->with(['adminphp_blocks', 'adminphp_revisions', 'adminphp_users', 'adminphp_password_reset_tokens', 'adminphp_settings', 'adminphp_media_usages', 'articles']);

it('names the publication index after its table', function () {
    expect(collect(Schema::getIndexes('articles'))->pluck('name'))->toContain('articles_publication_index');
});

it('renames the tables of an install made before the media library was renamed', function () {
    $migrationPath = __DIR__.'/../../database/migrations';
    $medias = require "{$migrationPath}/create_adminphp_medias_table.php";
    $usages = require "{$migrationPath}/create_adminphp_media_usages_table.php";

    // The tables as the former migrations created them, with a used image.
    Schema::drop('adminphp_media_usages');
    Schema::drop('adminphp_medias');
    Schema::create('adminphp_assets', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->string('disk', 50);
        $table->string('path');
        $table->string('name');
        $table->string('mime_type', 100);
        $table->unsignedBigInteger('size');
        $table->unsignedInteger('width')->nullable();
        $table->unsignedInteger('height')->nullable();
        $table->jsonb('variants')->nullable();
        $table->ulid('user_id')->nullable()->index();
        $table->timestamps();
        $table->index('created_at', 'adminphp_assets_created_at_index');
    });
    Schema::create('adminphp_asset_usages', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->ulid('asset_id');
        $table->string('usable_type', 100);
        $table->string('usable_id', 36);
        $table->unique(['asset_id', 'usable_type', 'usable_id'], 'adminphp_asset_usages_unique');
        $table->index(['usable_type', 'usable_id'], 'adminphp_asset_usages_usable_index');
    });

    $mediaId = (string) Str::ulid();
    DB::table('adminphp_assets')->insert(['id' => $mediaId, 'disk' => 'public', 'path' => 'adminphp/a.jpg', 'name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
    DB::table('adminphp_asset_usages')->insert(['id' => (string) Str::ulid(), 'asset_id' => $mediaId, 'usable_type' => 'article', 'usable_id' => 'x']);

    $medias->up();
    $usages->up();

    expect(Schema::hasTable('adminphp_assets'))->toBeFalse()
        ->and(Schema::hasTable('adminphp_asset_usages'))->toBeFalse()
        ->and(Media::query()->find($mediaId)?->name)->toBe('a.jpg')
        ->and(MediaUsage::query()->where('media_id', $mediaId)->count())->toBe(1)
        ->and(collect(Schema::getIndexes('adminphp_medias'))->pluck('name'))->toContain('adminphp_medias_created_at_index', 'adminphp_medias_user_id_index')
        ->and(collect(Schema::getIndexes('adminphp_media_usages'))->pluck('columns', 'name')->all())->toMatchArray([
            'adminphp_media_usages_unique' => ['media_id', 'usable_type', 'usable_id'],
            'adminphp_media_usages_usable_index' => ['usable_type', 'usable_id'],
        ]);

    // A content uses several medias: the unique index must be on the media, not only on the content.
    $otherMediaId = (string) Str::ulid();
    DB::table('adminphp_medias')->insert(['id' => $otherMediaId, 'disk' => 'public', 'path' => 'adminphp/b.jpg', 'name' => 'b.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
    MediaUsage::query()->create(['media_id' => $otherMediaId, 'usable_type' => 'article', 'usable_id' => 'x']);

    expect(MediaUsage::query()->where('usable_id', 'x')->count())->toBe(2);
});
