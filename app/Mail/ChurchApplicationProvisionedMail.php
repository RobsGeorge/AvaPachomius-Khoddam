<?php

namespace App\Mail;

use App\Models\Church;
use App\Models\ChurchApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChurchApplicationProvisionedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ChurchApplication $application,
        public Church $church,
        public string $adminUrl,
    ) {}

    public function build()
    {
        return $this->subject(__('church_applications.provisioned_admin_mail_subject', [
            'church' => $this->church->name,
        ]))
            ->view('emails.church-application-provisioned')
            ->with([
                'application' => $this->application,
                'church' => $this->church,
                'adminUrl' => $this->adminUrl,
                'emailTitle' => __('church_applications.provisioned_admin_mail_subject', [
                    'church' => $this->church->name,
                ]),
            ]);
    }
}
