<?php

declare(strict_types=1);

namespace App\MoonShine\Notifications;

use App\Events\MoonShineNotificationSent;
use Illuminate\Support\Collection;
use MoonShine\Crud\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Crud\Contracts\Notifications\NotificationButtonContract;
use MoonShine\Laravel\Notifications\MoonShineNotification;
use MoonShine\Support\Enums\Color;

class ReverbNotificationSystem implements MoonShineNotificationContract
{
    private MoonShineNotification $baseNotification;

    public function __construct()
    {
        // Use default MoonShine notification system for database operations
        $this->baseNotification = new MoonShineNotification;
    }

    /**
     * @param  array<int|string>  $ids
     */
    public function notify(
        string $message,
        ?NotificationButtonContract $button = null,
        array $ids = [],
        string|Color|null $color = null,
        ?string $icon = null
    ): void {
        // Resolve color value
        $colorValue = $color instanceof Color ? $color->value : ($color ?? Color::INFO->value);

        // 1. Save to Database using the default system
        $this->baseNotification->notify($message, $button, $ids, $color, $icon);

        // 2. Broadcast to WebSockets (Reverb)
        broadcast(new MoonShineNotificationSent(
            message: $message,
            ids: $ids,
            color: $colorValue,
            icon: $icon
        ));
    }

    /**
     * Delegate UI methods to the default MoonShine implementation.
     */
    public function getAll(): Collection
    {
        return $this->baseNotification->getAll();
    }

    public function readAll(): void
    {
        $this->baseNotification->readAll();
    }

    public function markAsRead(int|string $id): void
    {
        $this->baseNotification->markAsRead($id);
    }

    public function getReadAllRoute(): string
    {
        return $this->baseNotification->getReadAllRoute();
    }
}
