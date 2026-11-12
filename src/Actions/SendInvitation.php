<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\User;
use Gmarineau\AdminPhp\Notifications\InvitationNotification;

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
