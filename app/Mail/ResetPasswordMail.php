<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
    ) {}

    public function build()
    {
        $expireMinutes = config('auth.passwords.users.expire', 60);

        return $this->subject(__('password.reset_email_subject'))
            ->view('emails.reset_password')
            ->with([
                'user'          => $this->user,
                'resetUrl'      => $this->resetUrl,
                'expireMinutes' => $expireMinutes,
            ]);
    }
}
