<?php

namespace App\Mail;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant, public string $ownerName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to '.PlatformSetting::current()->displayName().'!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.accounts.welcome');
    }
}
