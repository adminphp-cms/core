<?php

use AdminPhp\AdminPhp;
use AdminPhp\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('adminphp-login|claire@example.com|127.0.0.1');
});

it('rejects guests from the admin API', function () {
    $this->getJson('/admin/api/modules')->assertUnauthorized();
    $this->getJson('/admin/api/session')->assertUnauthorized();
});

it('logs an admin user in and out', function () {
    $user = User::factory()->create(['email' => 'claire@example.com', 'password' => 'secret-password']);

    $this->postJson('/admin/api/session', ['email' => 'claire@example.com', 'password' => 'secret-password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'claire@example.com')
        ->assertJsonStructure(['csrfToken']);

    $this->assertAuthenticatedAs($user, 'adminphp');
    expect($user->fresh()->last_login_at)->not->toBeNull();

    $this->getJson('/admin/api/session')->assertOk()->assertJsonPath('data.id', $user->id);

    $this->deleteJson('/admin/api/session')->assertOk();

    $this->assertGuest('adminphp');
});

it('refuses wrong credentials', function () {
    User::factory()->create(['email' => 'claire@example.com']);

    $this->postJson('/admin/api/session', ['email' => 'claire@example.com', 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest('adminphp');
});

it('throttles repeated failed logins', function () {
    User::factory()->create(['email' => 'claire@example.com', 'password' => 'secret-password']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/admin/api/session', ['email' => 'claire@example.com', 'password' => 'wrong-password']);
    }

    $this->postJson('/admin/api/session', ['email' => 'claire@example.com', 'password' => 'secret-password'])
        ->assertTooManyRequests()
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest('adminphp');
});

it('keeps site users out of the admin', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->getJson('/admin/api/modules')->assertUnauthorized();
});

it('lets the application restrict logged in admin users further', function () {
    actingAsAdmin();
    app(AdminPhp::class)->auth(fn ($request) => $request->ip() === '10.0.0.1');

    $this->getJson('/admin/api/modules')->assertForbidden();
});
