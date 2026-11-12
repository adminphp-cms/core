<?php

use Gmarineau\AdminPhp\Enums\UserRole;
use Gmarineau\AdminPhp\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an admin user', function () {
    $this->artisan('adminphp:user', ['email' => 'claire@example.com', '--name' => 'Claire', '--password' => 'secret-password'])
        ->assertSuccessful();

    $user = User::query()->where('email', 'claire@example.com')->sole();

    expect($user->name)->toBe('Claire')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('creates an editor with the role option', function () {
    $this->artisan('adminphp:user', ['email' => 'claire@example.com', '--name' => 'Claire', '--password' => 'secret-password', '--role' => 'editor'])
        ->assertSuccessful();

    expect(User::query()->where('email', 'claire@example.com')->sole()->role)->toBe(UserRole::Editor);
});

it('resets the password of an existing admin user', function () {
    $user = User::factory()->create(['email' => 'claire@example.com', 'name' => 'Claire']);

    $this->artisan('adminphp:user', ['email' => 'claire@example.com', '--password' => 'new-password'])
        ->assertSuccessful();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->name)->toBe('Claire')
        ->and(User::query()->count())->toBe(1);
});

it('refuses a short password', function () {
    $this->artisan('adminphp:user', ['email' => 'claire@example.com', '--name' => 'Claire', '--password' => 'short'])
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
