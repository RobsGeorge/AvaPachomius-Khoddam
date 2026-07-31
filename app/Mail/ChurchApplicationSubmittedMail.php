<?php

namespace App\Mail;

use App\Models\ChurchApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChurchApplicationSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ChurchApplication $application,
    ) {}

    public function build()
    {
        $token = (string) $this->application->public_token;

        return $this->subject(__('church_applications.mail_subject'))
            ->view('emails.church-application-submitted')
            ->with([
                'application' => $this->application,
                'verifyUrl' => route('church-registration.verify', ['token' => $token]),
                'statusUrl' => route('church-registration.status', ['token' => $token]),
                'emailTitle' => __('church_applications.mail_subject'),
            ]);
    }
}
