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

class MonthlyBirthdayAnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $monthLabel;

    public function __construct(
        public Course $course,
        public Collection $birthdayStudents,
        public int $month,
        public int $year,
        public User $recipient
    ) {
        $this->monthLabel = Carbon::create($year, $month, 1)
            ->locale(app()->getLocale())
            ->translatedFormat('F Y');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('students.mail_subject', [
                'course' => $this->course->title,
                'month' => $this->monthLabel,
            ])
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.birthdays.monthly-announcement',
            with: [
                'course' => $this->course,
                'birthdayStudents' => $this->birthdayStudents,
                'recipient' => $this->recipient,
                'monthLabel' => $this->monthLabel,
                'emailTitle' => __('students.mail_subject', [
                    'course' => $this->course->title,
                    'month' => $this->monthLabel,
                ]),
                'headerSubtitle' => $this->course->title,
            ],
        );
    }
}
