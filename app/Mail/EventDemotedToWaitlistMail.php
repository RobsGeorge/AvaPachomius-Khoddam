<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventDemotedToWaitlistMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('events.mail_demoted_subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.events.demoted',
            with: [
                'event' => $this->event,
                'user' => $this->user,
                'emailTitle' => __('events.mail_demoted_subject', ['title' => $this->event->title]),
                'headerSubtitle' => $this->event->title,
            ],
        );
    }
}
