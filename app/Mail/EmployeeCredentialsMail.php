<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $employeeName,
        public readonly string $loginEmail,
        public readonly string $plainPassword,
        public readonly string $appName,
        public readonly string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->appName} Account Credentials",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-credentials',
            with: [
                'employeeName'  => $this->employeeName,
                'loginEmail'    => $this->loginEmail,
                'plainPassword' => $this->plainPassword,
                'appName'       => $this->appName,
                'appUrl'        => $this->appUrl,
            ],
        );
    }
}
