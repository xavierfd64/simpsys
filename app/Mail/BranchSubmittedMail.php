<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BranchSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $branch) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Branch Submitted for Approval — '.$this->branch->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.branches.submitted');
    }
}
