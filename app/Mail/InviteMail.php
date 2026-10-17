<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InviteMail extends Mailable implements ShouldQueue
{
    public function __construct(public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're invited to Nail Polish Collector");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invite');
    }
}
