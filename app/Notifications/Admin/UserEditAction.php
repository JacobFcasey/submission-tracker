<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserEditAction extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $actionType,
        private readonly string $message,
        private readonly array $meta = []
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return array_merge([
            'type' => $this->actionType,
            'message' => $this->message,
            'timestamp' => now()->toDateTimeString(),
        ], $this->meta);
    }
}
