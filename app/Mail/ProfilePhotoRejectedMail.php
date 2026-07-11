<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProfilePhotoRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function build()
    {
        return $this->subject(__('profile_photos.rejection_email_subject'))
            ->view('emails.profile-photo-rejected')
            ->with([
                'user' => $this->user,
                'emailTitle' => __('profile_photos.rejection_email_subject'),
                'headerSubtitle' => __('profile_photos.rejection_email_header'),
                'profileUrl' => route('profile'),
            ]);
    }
}
