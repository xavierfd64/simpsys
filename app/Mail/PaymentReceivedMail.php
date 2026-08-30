<?php

namespace App\Mail;

use App\Models\BillingPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BillingPayment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment Received — Thank You');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing.payment-received');
    }
}
