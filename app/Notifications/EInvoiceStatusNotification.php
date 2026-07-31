<?php

namespace App\Notifications;

use App\Models\EInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EInvoiceStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly EInvoice $eInvoice)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->eInvoice->invoice;
        $status = strtoupper($this->eInvoice->status);

        $mail = (new MailMessage)
            ->subject("e-Invoice {$status}: {$invoice->invoice_number}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("Your e-Invoice for invoice {$invoice->invoice_number} is now {$status}.")
            ->line('Total: RM '.number_format((float) $invoice->total_amount, 2));

        if ($this->eInvoice->validationUrl()) {
            $mail->action('View validated e-Invoice', $this->eInvoice->validationUrl());
        } elseif ($this->eInvoice->status === 'invalid') {
            $mail->line('Please sign in to view the validation errors and correct your e-Invoice profile before retrying.');
        }

        return $mail->line('This notification was generated automatically by Mueble Desk.');
    }
}
