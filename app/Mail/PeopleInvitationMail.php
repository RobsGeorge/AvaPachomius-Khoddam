<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PeopleInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Invitation $invitation,
        public string $plainToken,
        public string $otp,
    ) {}

    public function build()
    {
        $claimUrl = url('/invitations/'.$this->plainToken);

        return $this->subject(__('people_onboarding.invite_email_subject'))
            ->view('emails.people_invitation')
            ->with([
                'user' => $this->user,
                'invitation' => $this->invitation,
                'otp' => $this->otp,
                'claimUrl' => $claimUrl,
                'emailTitle' => __('people_onboarding.invite_email_subject'),
            ]);
    }
}
