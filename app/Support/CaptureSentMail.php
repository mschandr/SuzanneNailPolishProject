<?php

namespace App\Support;

use Illuminate\Mail\Events\MessageSent;

/**
 * Development aid (MAIL_CAPTURE=true): appends every message the app sends, including its full body, to a file in
 * storage, so sign-in links and invites can be read without a mail server. Never enable it in production: the
 * messages contain working sign-in links.
 */
// Lives outside app/Listeners on purpose: Laravel auto-registers that folder, and this one must only listen when asked.
class CaptureSentMail
{
    public function handle(MessageSent $event): void
    {
        $path = config('nailpolish.mail_capture_path');
        $message = $event->message;

        $entry = '['.now()->toDateTimeString().'] '.str_repeat('=', 60)."\n"
            .'From: '.$this->addresses($message->getFrom())."\n"
            .'To: '.$this->addresses($message->getTo())."\n"
            .($message->getCc() ? 'Cc: '.$this->addresses($message->getCc())."\n" : '')
            .'Subject: '.$message->getSubject()."\n\n"
            .$this->body($message)."\n\n";

        // Best effort: capturing must never stop a real email from being sent.
        try {
            file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function addresses(array $addresses): string
    {
        return collect($addresses)->map(fn ($a) => $a->toString())->join(', ');
    }

    /** Plain text if there is any, then the HTML, so both versions are on record. */
    private function body($message): string
    {
        $parts = [];

        if (filled($text = $message->getTextBody())) {
            $parts[] = "--- text ---\n".$text;
        }

        if (filled($html = $message->getHtmlBody())) {
            $parts[] = "--- html ---\n".$html;
        }

        return implode("\n", $parts);
    }
}
