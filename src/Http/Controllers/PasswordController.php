<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * "Forgotten password" links and the page where a user chooses their password,
 * from such a link or from an invitation. Choosing it logs the user in.
 */
class PasswordController
{
    public function __construct(protected AdminPhp $adminPhp) {}

    /**
     * Always answers the same way, so the form does not reveal which emails have an account.
     */
    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::query()->where('email', mb_strtolower(trim($validated['email'])))->first();

        if ($user?->isActive()) {
            $this->adminPhp->passwordBroker()->sendResetLink(['email' => $user->email]);
        }

        return response()->json(['message' => 'Si un compte existe pour cette adresse, un lien vient de lui être envoyé.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'invitation' => ['boolean'],
        ]);

        $broker = $request->boolean('invitation') ? AdminPhp::INVITATION_BROKER : AdminPhp::PASSWORD_BROKER;
        $user = User::query()->where('email', mb_strtolower(trim($validated['email'])))->first();

        if ($user === null || ! $user->isActive()) {
            throw ValidationException::withMessages(['token' => $this->invalidLinkMessage()]);
        }

        $status = $this->adminPhp->passwordBroker($broker)->reset(
            [
                'email' => $user->email,
                'token' => $validated['token'],
                'password' => $validated['password'],
            ],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => $this->invalidLinkMessage()]);
        }

        $this->adminPhp->guard()->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'data' => $user->toAdminArray(),
            'csrfToken' => $request->session()->token(),
        ]);
    }

    protected function invalidLinkMessage(): string
    {
        return 'Ce lien n’est plus valable. Demandez-en un nouveau.';
    }
}
