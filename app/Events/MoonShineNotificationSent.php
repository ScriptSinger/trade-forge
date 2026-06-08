<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MoonShine\Support\Enums\Color;

class MoonShineNotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $message,
        public array $ids = [],
        public string $color = 'info',
        public ?string $icon = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('moonshine-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.sent';
    }
}
