<?php

use Illuminate\Support\Facades\Schema;

it('keeps every index name under the 64 character limit of MySQL', function (string $table) {
    $tooLongNames = collect(Schema::getIndexes($table))
        ->pluck('name')
        ->filter(fn (string $name): bool => strlen($name) > 64)
        ->all();

    expect($tooLongNames)->toBe([]);
})->with(['adminphp_blocks', 'adminphp_revisions', 'adminphp_users', 'adminphp_password_reset_tokens', 'adminphp_settings', 'articles']);

it('names the publication index after its table', function () {
    expect(collect(Schema::getIndexes('articles'))->pluck('name'))->toContain('articles_publication_index');
});
