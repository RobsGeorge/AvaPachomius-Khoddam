<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $notificationId,
        public int $userId,
    ) {}

    public function handle(WhatsAppNotificationService $whatsapp): void
    {
        $notification = UserNotification::query()->find($this->notificationId);
        $user = User::query()->find($this->userId);

        if (! $notification || ! $user) {
            return;
        }

        $whatsapp->send($notification, $user);
    }
}
