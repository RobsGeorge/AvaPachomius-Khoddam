<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOTPEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public ?User $user = null,
    ) {}

    public function build()
    {
        return $this->subject(__('auth.otp_email_subject'))
            ->view('emails.send_otp')
            ->with([
                'otp'            => $this->otp,
                'user'           => $this->user,
                'emailTitle'     => __('auth.otp_email_subject'),
                'headerSubtitle' => __('auth.otp_title'),
            ]);
    }
}
