<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Support\CaptureSentMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailCaptureTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'mailcap');
        unlink($this->file);
        config(['nailpolish.mail_capture_path' => $this->file]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    private function register(): void
    {
        Event::listen(MessageSent::class, CaptureSentMail::class);
    }

    public function test_it_is_off_by_default(): void
    {
        $this->assertFalse(config('nailpolish.mail_capture'));

        Mail::to('a@example.com')->send(new MagicLinkMail('https://example.test/login/abc'));

        $this->assertFileDoesNotExist($this->file);
    }

    public function test_a_sent_email_is_written_in_full(): void
    {
        $this->register();

        Mail::to('someone@example.com')->send(new MagicLinkMail('https://example.test/login/SECRETTOKEN'));

        $log = file_get_contents($this->file);
        $this->assertStringContainsString('To: someone@example.com', $log);
        $this->assertStringContainsString('Subject: Your sign-in link', $log);
        $this->assertStringContainsString('https://example.test/login/SECRETTOKEN', $log);
        $this->assertStringContainsString('expires in 15 minutes', $log);
    }

    public function test_each_email_is_appended(): void
    {
        $this->register();

        Mail::to('one@example.com')->send(new MagicLinkMail('https://example.test/login/one'));
        Mail::to('two@example.com')->send(new MagicLinkMail('https://example.test/login/two'));

        $log = file_get_contents($this->file);
        $this->assertStringContainsString('one@example.com', $log);
        $this->assertStringContainsString('two@example.com', $log);
        $this->assertSame(2, substr_count($log, 'Subject: Your sign-in link'));
    }

    public function test_an_unwritable_path_never_stops_the_email(): void
    {
        config(['nailpolish.mail_capture_path' => '/nonexistent-dir/mail.log']);
        $this->register();

        Mail::to('a@example.com')->send(new MagicLinkMail('https://example.test/login/abc'));

        $this->assertTrue(true, 'no exception reached the caller');
    }
}
