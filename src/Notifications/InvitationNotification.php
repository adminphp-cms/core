<?php

namespace Gmarineau\AdminPhp\Notifications;

use Gmarineau\AdminPhp\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a new admin user to choose their password.
 */
class InvitationNotification extends Notification
{
    public function __construct(public string $token, public ?string $invitedBy = null) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $days = intdiv((int) config('adminphp.passwords.invitation_expire', 10080), 1440);
        $appName = (string) config('app.name');

        return (new MailMessage)
            ->subject("Votre accès à l’administration de {$appName}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->invitedBy !== null
                ? "{$this->invitedBy} vous invite à rejoindre l’administration de {$appName}."
                : "Vous êtes invité à rejoindre l’administration de {$appName}.")
            ->action('Choisir mon mot de passe', route('adminphp.spa', [
                'view' => "invitation/{$this->token}",
                'email' => $notifiable->email,
            ]))
            ->line("Ce lien est valable {$days} jours.");
    }
}
