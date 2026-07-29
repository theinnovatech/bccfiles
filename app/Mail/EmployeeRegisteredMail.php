<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly string $appName,
        public readonly string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->appName} — Employee record registered",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-registered',
            with: [
                'employeeName' => $this->employee->name,
                'employeeNumber' => $this->employee->employee_number,
                'departmentName' => $this->employee->department?->name ?? '—',
                'position' => $this->employee->position ?: '—',
                'contactEmail' => $this->employee->contact_email,
                'appName' => $this->appName,
                'appUrl' => $this->appUrl,
            ],
        );
    }
}
