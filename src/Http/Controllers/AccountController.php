<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * The logged in user's own account: name, email and password. Changing the
 * email or the password asks for the current password.
 */
class AccountController
{
    public function __construct(protected AdminPhp $adminPhp) {}

    public function update(Request $request): JsonResponse
    {
        $user = $this->currentUser();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
        ]);

        $email = mb_strtolower(trim($validated['email']));

        if ($email !== $user->email) {
            $this->ensureCurrentPassword($user, $validated['current_password'] ?? null);
        }

        $user->fill(['name' => trim($validated['name']), 'email' => $email])->save();

        return response()->json(['data' => $user->toAdminArray()]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $this->currentUser();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $this->ensureCurrentPassword($user, $validated['current_password']);

        // A new remember token signs out the "stay logged in" cookies of other browsers.
        $user->forceFill(['password' => $validated['password']])->setRememberToken(Str::random(60));
        $user->save();

        $request->session()->regenerate();

        return response()->json([
            'data' => $user->toAdminArray(),
            'csrfToken' => $request->session()->token(),
        ]);
    }

    protected function currentUser(): User
    {
        return $this->adminPhp->user() ?? abort(401);
    }

    protected function ensureCurrentPassword(User $user, ?string $currentPassword): void
    {
        if ($currentPassword === null || $user->password === null || ! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }
    }
}
