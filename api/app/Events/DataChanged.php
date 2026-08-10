<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DataChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $type;
    public ?int $projectId;

    /**
     * Create a new event instance.
     *
     * @param int $userId  The user to notify
     * @param string $type The data type that changed (tasks, projects, notifications, etc.)
     * @param int|null $projectId  Optional project context
     */
    public function __construct(
        public int $userId,
        string $type,
        ?int $projectId = null
    ) {
        $this->type = $type;
        $this->projectId = $projectId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'data.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'projectId' => $this->projectId,
        ];
    }
}
