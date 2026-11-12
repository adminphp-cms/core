<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\InviteUser;
use Gmarineau\AdminPhp\Actions\SendInvitation;
use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Enums\UserRole;
use Gmarineau\AdminPhp\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin users, managed by admins: invite, edit, deactivate and reactivate.
 * Nobody can change their own role or deactivate themselves, so there is
 * always at least one active admin left.
 */
class UserController
{
    public function __construct(protected AdminPhp $adminPhp) {}

    public function index(): JsonResponse
    {
        $users = User::query()->orderBy('name')->get();

        return response()->json([
            'data' => $users->map(fn (User $user): array => $user->toManagementArray())->all(),
        ]);
    }

    public function store(Request $request, InviteUser $inviteUser): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $user = $inviteUser->handle(
            trim($validated['name']),
            mb_strtolower(trim($validated['email'])),
            UserRole::from($validated['role']),
            $this->adminPhp->user(),
        );

        return response()->json(['data' => $user->fresh()?->toManagementArray()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $role = UserRole::from($validated['role']);

        if ($this->isCurrentUser($user) && $role !== $user->role) {
            throw ValidationException::withMessages(['role' => 'Vous ne pouvez pas changer votre propre rôle.']);
        }

        $user->fill([
            'name' => trim($validated['name']),
            'email' => mb_strtolower(trim($validated['email'])),
            'role' => $role,
        ])->save();

        return response()->json(['data' => $user->toManagementArray()]);
    }

    public function resendInvitation(string $id, SendInvitation $sendInvitation): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if (! $user->hasPendingInvitation() || ! $user->isActive()) {
            throw ValidationException::withMessages(['user' => 'Cette personne n’a pas d’invitation en attente.']);
        }

        $sendInvitation->handle($user, $this->adminPhp->user());

        return response()->json(['data' => $user->toManagementArray()]);
    }

    public function deactivate(string $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if ($this->isCurrentUser($user)) {
            throw ValidationException::withMessages(['user' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        if ($user->isActive()) {
            $user->deactivate();
        }

        return response()->json(['data' => $user->toManagementArray()]);
    }

    public function activate(string $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $user->activate();

        return response()->json(['data' => $user->toManagementArray()]);
    }

    protected function isCurrentUser(User $user): bool
    {
        return $this->adminPhp->user()?->is($user) ?? false;
    }
}
