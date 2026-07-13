<?php

namespace App\Mail;

use App\Models\RoleAssignmentEmailTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RoleAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, string> $replacements */
    public function __construct(
        public User $user,
        public RoleAssignmentEmailTemplate $template,
        public array $replacements = [],
    ) {}

    public function build()
    {
        $subject = $this->renderTemplate($this->template->subject);
        $body = $this->renderTemplate($this->template->body_html);

        return $this->subject($subject)
            ->view('emails.role-assignment')
            ->with([
                'user' => $this->user,
                'bodyHtml' => $body,
                'emailTitle' => $subject,
                'headerSubtitle' => __('rbac.email_header'),
            ]);
    }

    private function renderTemplate(string $content): string
    {
        $rendered = $content;

        foreach ($this->replacements as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', $value, $rendered);
        }

        return $rendered;
    }
}
