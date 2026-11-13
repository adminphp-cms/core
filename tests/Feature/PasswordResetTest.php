<?php

use AdminPhp\AdminPhp;
use AdminPhp\Models\User;
use AdminPhp\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('emails a reset link to an existing user', function () {
    $user = User::factory()->create(['email' => 'lea@example.com']);

    $this->postJson('/admin/api/password/forgot', ['email' => 'Lea@example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, "/admin/reset-password/{$notification->token}") && str_contains($url, 'email=lea%40example.com');
    });
});

it('answers the same way for an unknown or deactivated email', function () {
    $deactivated = User::factory()->deactivated()->create(['email' => 'old@example.com']);

    $unknown = $this->postJson('/admin/api/password/forgot', ['email' => 'nobody@example.com'])->assertOk()->json('message');
    $this->postJson('/admin/api/password/forgot', ['email' => 'old@example.com'])->assertOk()->assertJsonPath('message', $unknown);

    Notification::assertNothingSentTo($deactivated);
});

it('sets the new password from a reset link and logs the user in', function () {
    $user = User::factory()->create(['email' => 'lea@example.com']);
    $token = app(AdminPhp::class)->passwordBroker()->createToken($user);

    $this->postJson('/admin/api/password/reset', [
        'token' => $token,
        'email' => 'lea@example.com',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonStructure(['csrfToken']);

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue();
    $this->assertAuthenticatedAs($user, 'adminphp');
});

it('accepts an invitation and logs the invited user in', function () {
    $user = User::factory()->invited()->create(['email' => 'lea@example.com']);
    $token = app(AdminPhp::class)->passwordBroker(AdminPhp::INVITATION_BROKER)->createToken($user);

    $this->postJson('/admin/api/password/reset', [
        'token' => $token,
        'email' => 'lea@example.com',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
        'invitation' => true,
    ])->assertOk();

    expect($user->fresh()->hasPendingInvitation())->toBeFalse();
    $this->assertAuthenticatedAs($user, 'adminphp');
});

it('keeps an invitation valid longer than a reset link', function () {
    $user = User::factory()->invited()->create(['email' => 'lea@example.com']);
    $token = app(AdminPhp::class)->passwordBroker(AdminPhp::INVITATION_BROKER)->createToken($user);

    $this->travel(3)->days();

    $payload = ['token' => $token, 'email' => 'lea@example.com', 'password' => 'new-secret-password', 'password_confirmation' => 'new-secret-password'];

    $this->postJson('/admin/api/password/reset', $payload)->assertUnprocessable()->assertJsonValidationErrors(['token']);
    $this->postJson('/admin/api/password/reset', [...$payload, 'invitation' => true])->assertOk();
});

it('refuses a wrong token, a short password and a deactivated user', function () {
    $user = User::factory()->create(['email' => 'lea@example.com']);
    $token = app(AdminPhp::class)->passwordBroker()->createToken($user);
    $payload = ['token' => $token, 'email' => 'lea@example.com', 'password' => 'new-secret-password', 'password_confirmation' => 'new-secret-password'];

    $this->postJson('/admin/api/password/reset', [...$payload, 'token' => 'wrong'])->assertJsonValidationErrors(['token']);
    $this->postJson('/admin/api/password/reset', [...$payload, 'password' => 'short', 'password_confirmation' => 'short'])->assertJsonValidationErrors(['password']);

    $user->deactivate();
    $this->postJson('/admin/api/password/reset', $payload)->assertJsonValidationErrors(['token']);

    $this->assertGuest('adminphp');
});
