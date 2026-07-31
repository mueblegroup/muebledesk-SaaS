<?php

namespace App\Mail;

use App\Models\User; // Make sure this is imported
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public User $user; // Public property to make $user available in the view
    public string $password; // Public property for the temporary password

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your New Account Details - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.client-account-created', // Points to the Blade view
            with: [
                // Pass the public properties directly to the view
                'user' => $this->user, // Pass the User object
                'password' => $this->password, // Pass the temporary password
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}