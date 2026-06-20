<?php

namespace Tests\Unit\Events;

use App\Mail\EventCancelledMail;
use App\Mail\EventDemotedToWaitlistMail;
use App\Mail\EventReservationConfirmedMail;
use App\Mail\EventReservationWaitlistMail;
use App\Mail\EventWaitlistPromotedMail;
use Tests\Support\EventModuleTestCase;

class EventMailRenderTest extends EventModuleTestCase
{
    public function test_event_mails_render_without_undefined_variables(): void
    {
        $admin = $this->createUser(['email' => 'mail-admin@example.com']);
        $user = $this->createUser(['email' => 'mail-user@example.com', 'first_name' => 'Mail']);
        $event = $this->createEvent($admin, ['title' => 'Mail Test Event']);

        $mailables = [
            new EventReservationConfirmedMail($event, $user),
            new EventReservationWaitlistMail($event, $user),
            new EventWaitlistPromotedMail($event, $user),
            new EventDemotedToWaitlistMail($event, $user),
            new EventCancelledMail($event, $user),
        ];

        foreach ($mailables as $mailable) {
            $html = $mailable->render();
            $this->assertStringContainsString('Mail Test Event', $html);
            $this->assertStringContainsString('Mail', $html);
        }
    }
}
