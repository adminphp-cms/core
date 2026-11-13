<?php

use AdminPhp\AdminPhp;
use AdminPhp\Enums\UserRole;
use AdminPhp\Models\User;
use AdminPhp\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('lists the admin users for an admin', function () {
    $admin = actingAsAdmin();
    User::factory()->editor()->invited()->create(['name' => 'Zoé']);

    $this->getJson('/admin/api/users')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $admin->id)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.1.status', 'invited')
        ->assertJsonPath('data.1.roleLabel', 'Éditeur');
});

it('keeps editors out of user management', function () {
    $editor = User::factory()->editor()->create();
    $this->actingAs($editor, 'adminphp');

    $this->getJson('/admin/api/users')->assertForbidden();
    $this->postJson('/admin/api/users', ['name' => 'Léa', 'email' => 'lea@example.com', 'role' => 'admin'])->assertForbidden();
    $this->getJson('/admin/api/session')->assertOk()->assertJsonPath('data.canManageUsers', false);
});

it('invites a user by email', function () {
    $admin = actingAsAdmin();

    $this->postJson('/admin/api/users', ['name' => ' Léa ', 'email' => 'Lea@Example.com', 'role' => 'editor'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'lea@example.com')
        ->assertJsonPath('data.status', 'invited');

    $user = User::query()->where('email', 'lea@example.com')->sole();

    expect($user->name)->toBe('Léa')
        ->and($user->role)->toBe(UserRole::Editor)
        ->and($user->password)->toBeNull();

    Notification::assertSentTo($user, InvitationNotification::class, fn (InvitationNotification $notification): bool => $notification->invitedBy === $admin->name
        && app(AdminPhp::class)->passwordBroker(AdminPhp::INVITATION_BROKER)->tokenExists($user, $notification->token));
});

it('refuses to invite an email that already has an account', function () {
    actingAsAdmin();
    User::factory()->create(['email' => 'lea@example.com']);

    $this->postJson('/admin/api/users', ['name' => 'Léa', 'email' => 'lea@example.com', 'role' => 'editor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    Notification::assertNothingSent();
});

it('lets an invited user that has not come yet log in only after choosing a password', function () {
    User::factory()->invited()->create(['email' => 'lea@example.com']);

    $this->postJson('/admin/api/session', ['email' => 'lea@example.com', 'password' => ''])->assertUnprocessable();
    $this->postJson('/admin/api/session', ['email' => 'lea@example.com', 'password' => 'anything'])->assertUnprocessable();

    $this->assertGuest('adminphp');
});

it('sends a pending invitation again', function () {
    actingAsAdmin();
    $user = User::factory()->invited()->create();

    $this->postJson("/admin/api/users/{$user->id}/invitation")->assertOk();

    Notification::assertSentTo($user, InvitationNotification::class);
});

it('does not resend an invitation to a user who has a password', function () {
    actingAsAdmin();
    $user = User::factory()->create();

    $this->postJson("/admin/api/users/{$user->id}/invitation")->assertUnprocessable();

    Notification::assertNothingSent();
});

it('updates the name, email and role of a user', function () {
    actingAsAdmin();
    $user = User::factory()->editor()->create();

    $this->putJson("/admin/api/users/{$user->id}", ['name' => 'Léa Martin', 'email' => 'lea.martin@example.com', 'role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.role', 'admin');

    expect($user->fresh())
        ->name->toBe('Léa Martin')
        ->email->toBe('lea.martin@example.com')
        ->role->toBe(UserRole::Admin);
});

it('does not let an admin change their own role', function () {
    $admin = actingAsAdmin();

    $this->putJson("/admin/api/users/{$admin->id}", ['name' => 'Me', 'email' => $admin->email, 'role' => 'editor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    $this->putJson("/admin/api/users/{$admin->id}", ['name' => 'Me', 'email' => $admin->email, 'role' => 'admin'])->assertOk();

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});

it('deactivates and reactivates a user', function () {
    actingAsAdmin();
    $user = User::factory()->create(['remember_token' => 'remembered']);

    $this->postJson("/admin/api/users/{$user->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.status', 'deactivated');

    expect($user->fresh()->isActive())->toBeFalse()
        ->and($user->fresh()->getRememberToken())->not->toBe('remembered');

    $this->postJson("/admin/api/users/{$user->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('does not let an admin deactivate themselves', function () {
    $admin = actingAsAdmin();

    $this->postJson("/admin/api/users/{$admin->id}/deactivate")->assertUnprocessable();

    expect($admin->fresh()->isActive())->toBeTrue();
});

it('refuses to log a deactivated user in', function () {
    User::factory()->deactivated()->create(['email' => 'lea@example.com', 'password' => 'secret-password']);

    $this->postJson('/admin/api/session', ['email' => 'lea@example.com', 'password' => 'secret-password'])
        ->assertUnprocessable();

    $this->assertGuest('adminphp');
});

it('logs a user out on their next request once deactivated', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'adminphp');

    $user->deactivate();

    $this->getJson('/admin/api/modules')->assertUnauthorized();
    $this->assertGuest('adminphp');
});
