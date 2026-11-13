<?php

use AdminPhp\Models\User;
use Illuminate\Support\Facades\Hash;

it('updates the name of the logged in user', function () {
    $user = actingAsAdmin();

    $this->putJson('/admin/api/account', ['name' => ' Claire Dubois ', 'email' => $user->email])
        ->assertOk()
        ->assertJsonPath('data.name', 'Claire Dubois');

    expect($user->fresh()->name)->toBe('Claire Dubois');
});

it('asks for the current password to change the email', function () {
    $user = User::factory()->create(['email' => 'claire@example.com', 'password' => 'secret-password']);
    $this->actingAs($user, 'adminphp');

    $this->putJson('/admin/api/account', ['name' => $user->name, 'email' => 'claire.dubois@example.com'])
        ->assertJsonValidationErrors(['current_password']);

    $this->putJson('/admin/api/account', ['name' => $user->name, 'email' => 'Claire.Dubois@example.com', 'current_password' => 'secret-password'])
        ->assertOk();

    expect($user->fresh()->email)->toBe('claire.dubois@example.com');
});

it('refuses an email already used by another user', function () {
    $user = actingAsAdmin();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->putJson('/admin/api/account', ['name' => $user->name, 'email' => 'taken@example.com', 'current_password' => 'password'])
        ->assertJsonValidationErrors(['email']);
});

it('changes the password with the current one and keeps the user logged in', function () {
    $user = User::factory()->create(['password' => 'secret-password', 'remember_token' => 'remembered']);
    $this->actingAs($user, 'adminphp');

    $this->putJson('/admin/api/account/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertJsonValidationErrors(['current_password']);

    $this->putJson('/admin/api/account/password', [
        'current_password' => 'secret-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertOk()->assertJsonStructure(['csrfToken']);

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->getRememberToken())->not->toBe('remembered');
    $this->assertAuthenticatedAs($user, 'adminphp');
});

it('lets editors manage their own account', function () {
    $editor = User::factory()->editor()->create();
    $this->actingAs($editor, 'adminphp');

    $this->putJson('/admin/api/account', ['name' => 'Éditrice', 'email' => $editor->email])->assertOk();
});

it('keeps guests out of the account endpoints', function () {
    $this->putJson('/admin/api/account', ['name' => 'X', 'email' => 'x@example.com'])->assertUnauthorized();
    $this->putJson('/admin/api/account/password', [])->assertUnauthorized();
});
