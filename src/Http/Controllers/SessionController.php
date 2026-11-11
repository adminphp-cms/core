<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login and logout of admin users. The session is regenerated on both,
 * so the new CSRF token is sent back to the admin front.
 */
class SessionController
{
    protected const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(protected AdminPhp $adminPhp) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->adminPhp->user()?->toAdminArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $throttleKey = 'adminphp-login|'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ])->status(429);
        }

        $isAuthenticated = $this->adminPhp->guard()->attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $request->boolean('remember'),
        );

        if (! $isAuthenticated) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = $this->adminPhp->user();
        $user?->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'data' => $user?->toAdminArray(),
            'csrfToken' => $request->session()->token(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->adminPhp->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['csrfToken' => $request->session()->token()]);
    }
}
