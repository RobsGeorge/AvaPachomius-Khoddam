<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventWaitlistPromotedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $checkInUrl;

    public function __construct(public Event $event, public User $user)
    {
        $this->checkInUrl = $event->signedCheckInUrlFor($user);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('events.mail_promoted_subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.events.promoted',
            with: [
                'checkInUrl' => $this->checkInUrl,
                'emailTitle' => __('events.mail_promoted_subject', ['title' => $this->event->title]),
                'headerSubtitle' => $this->event->title,
            ],
        );
    }
}
