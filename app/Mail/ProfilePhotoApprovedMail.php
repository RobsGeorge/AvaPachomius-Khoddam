<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProfilePhotoApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $dashboardUrl;

    public function __construct(
        public User $user,
    ) {
        $this->dashboardUrl = route('dashboard');
    }

    public function build()
    {
        return $this->subject(__('profile_photos.approval_email_subject'))
            ->view('emails.profile-photo-approved')
            ->with([
                'user' => $this->user,
                'emailTitle' => __('profile_photos.approval_email_subject'),
                'headerSubtitle' => __('profile_photos.approval_email_header'),
                'dashboardUrl' => $this->dashboardUrl,
            ]);
    }
}
