<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public UserNotification $notification,
    ) {}

    public function build()
    {
        return $this->subject($this->notification->title)
            ->view('emails.notification')
            ->with([
                'user' => $this->user,
                'notification' => $this->notification,
                'actionUrl' => $this->notification->action_url,
            ]);
    }
}
