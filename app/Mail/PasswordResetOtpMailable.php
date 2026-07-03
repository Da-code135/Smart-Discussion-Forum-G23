<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp  // plaintext 6-digit code — stored hashed in DB, sent once here
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Studdit Password Reset Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.password-reset-otp',
            with: [
                'user' => $this->user,
                'otp'  => $this->otp,
            ],
        );
    }
}
