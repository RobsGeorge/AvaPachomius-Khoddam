<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyBirthdayAnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $dateLabel;

    public function __construct(
        public Course $course,
        public Collection $birthdayStudents,
        public Carbon $on,
        public User $recipient
    ) {
        $this->dateLabel = $on->copy()
            ->locale(app()->getLocale())
            ->translatedFormat('j F Y');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('students.mail_subject_daily', [
                'course' => $this->course->title,
                'date' => $this->dateLabel,
            ])
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.birthdays.daily-announcement',
            with: [
                'course' => $this->course,
                'birthdayStudents' => $this->birthdayStudents,
                'recipient' => $this->recipient,
                'dateLabel' => $this->dateLabel,
                'emailTitle' => __('students.mail_subject_daily', [
                    'course' => $this->course->title,
                    'date' => $this->dateLabel,
                ]),
                'headerSubtitle' => $this->course->title,
                'rosterUrl' => url(route('students.roster', ['course' => $this->course->course_id])),
            ],
        );
    }
}
