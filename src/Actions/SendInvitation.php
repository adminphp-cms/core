<?php

namespace AdminPhp\Actions;

use AdminPhp\AdminPhp;
use AdminPhp\Models\User;
use AdminPhp\Notifications\InvitationNotification;

/**
 * Emails an invited user a link to choose their password. Sending it again
 * replaces the previous link.
 */
class SendInvitation
{
    public function __construct(protected AdminPhp $adminPhp) {}

    public function handle(User $user, ?User $invitedBy = null): void
    {
        $token = $this->adminPhp->passwordBroker(AdminPhp::INVITATION_BROKER)->createToken($user);

        $user->notify(new InvitationNotification($token, $invitedBy?->name));
    }
}
