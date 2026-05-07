<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification
{
    use Queueable;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vérifiez votre adresse e-mail')
            ->greeting('Bonjour !')
            ->line('Merci de vous inscrire. Veuillez utiliser le code ci-dessous pour vérifier votre adresse e-mail.')
            ->line('Votre code de vérification :')
            ->line('**' . $this->code . '**')
            ->line('Ce code est valable pendant 15 minutes.')
            ->line('Si vous n\'avez pas créé de compte, aucune action n\'est requise.')
            ->salutation('Cordialement, ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
