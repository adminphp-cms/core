<?php

namespace Gmarineau\AdminPhp\Notifications;

use Gmarineau\AdminPhp\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The link an admin user follows to choose a new password.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutes = (int) config('adminphp.passwords.reset_expire', 60);

        return (new MailMessage)
            ->subject('Réinitialiser votre mot de passe')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Vous avez demandé à changer le mot de passe de votre compte d’administration.')
            ->action('Choisir un nouveau mot de passe', route('adminphp.spa', [
                'view' => "reset-password/{$this->token}",
                'email' => $notifiable->email,
            ]))
            ->line("Ce lien est valable {$minutes} minutes.")
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez cet email : votre mot de passe ne change pas.');
    }
}
