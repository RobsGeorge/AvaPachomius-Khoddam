<?php

namespace App\Mail;

use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChurchFounderReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Church $church,
        public ChurchApplication $application,
        public string $loginUrl,
        public string $churchUrl,
    ) {}

    public function build()
    {
        return $this->subject(__('church_applications.founder_ready_mail_subject', [
            'church' => $this->church->preferredShortName(),
        ]))
            ->view('emails.church-founder-ready')
            ->with([
                'user' => $this->user,
                'church' => $this->church,
                'application' => $this->application,
                'loginUrl' => $this->loginUrl,
                'churchUrl' => $this->churchUrl,
                'emailTitle' => __('church_applications.founder_ready_mail_subject', [
                    'church' => $this->church->preferredShortName(),
                ]),
            ]);
    }
}
