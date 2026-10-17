<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NoAccountMail extends Mailable implements ShouldQueue
{
    public function __construct(public string $requestUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'About your sign-in request');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.no-account');
    }
}
