<?php

use AdminPhp\AdminPhp;
use AdminPhp\Models\User;
use AdminPhp\Tests\Console\InstallCommandTestCase;
use AdminPhp\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(InstallCommandTestCase::class)->in('Console');

/**
 * Logs a new admin user in for the current test.
 */
function actingAsAdmin(): User
{
    $user = User::factory()->create();

    test()->actingAs($user, app(AdminPhp::class)->guardName());

    return $user;
}

/**
 * Creates an article through the admin API and returns its id.
 *
 * @param  array<string, mixed>  $values
 */
function createArticleItem(array $values = []): string
{
    return test()->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Version 1'],
        ...$values,
    ])->assertCreated()->json('data.id');
}

/**
 * @param  array<string, mixed>  $payload
 */
function publishArticleItem(string $id, array $payload = []): void
{
    test()->postJson("/admin/api/modules/article/items/{$id}/publish", $payload)->assertOk();
}
