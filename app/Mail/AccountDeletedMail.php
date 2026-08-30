<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public bool $permanent,
    ) {
        $locale = $user->communication_locale ?: $user->ui_locale;
        if (is_string($locale) && in_array($locale, ['ar', 'en'], true)) {
            $this->locale($locale);
        } else {
            $this->locale('ar');
        }
    }

    public function build()
    {
        return $this->subject(__('user_deletion.email_subject'))
            ->view('emails.account-deleted')
            ->with([
                'user' => $this->user,
                'permanent' => $this->permanent,
                'emailTitle' => __('user_deletion.email_subject'),
                'headerSubtitle' => __('user_deletion.email_header'),
            ]);
    }
}
