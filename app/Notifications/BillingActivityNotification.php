<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $companyName,
        public readonly string $headline,
        public readonly array $details = [],
        public readonly ?string $actionUrl = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = data_get($notifiable, 'name');
        $greeting = filled($recipientName) ? 'Hello '.$recipientName.',' : 'Hello,';

        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting($greeting)
            ->line($this->headline)
            ->line('Company: '.$this->companyName);

        foreach ($this->details as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $mail->line($label.': '.$value);
        }

        if ($this->actionUrl) {
            $mail->action('View Plan & Billing', $this->actionUrl);
        }

        return $mail
            ->line('If you did not expect this billing activity, review the account immediately.')
            ->salutation('MuebleDesk');
    }
}
