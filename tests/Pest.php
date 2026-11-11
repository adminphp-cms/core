<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\User;
use Gmarineau\AdminPhp\Tests\Console\InstallCommandTestCase;
use Gmarineau\AdminPhp\Tests\TestCase;
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
