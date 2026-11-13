<?php

namespace AdminPhp\Actions;

use AdminPhp\Enums\UserRole;
use AdminPhp\Models\User;

/**
 * Creates an admin user without a password and emails them an invitation.
 */
class InviteUser
{
    public function __construct(protected SendInvitation $sendInvitation) {}

    public function handle(string $name, string $email, UserRole $role, ?User $invitedBy = null): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ]);

        $this->sendInvitation->handle($user, $invitedBy);

        return $user;
    }
}
