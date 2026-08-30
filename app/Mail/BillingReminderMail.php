<?php

namespace App\Mail;

use App\Support\BillingStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BillingStatement $statement) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Upcoming Renewal — Due '.$this->statement->subscription->current_period_end->format('M j, Y'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing.reminder');
    }
}
