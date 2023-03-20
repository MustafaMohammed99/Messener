<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     *
     * @var \App\Models\Message $message
     */
    public $message;


    /**
     * @param \App\Models\Message $message
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $other_users = $this->message->conversation->participants()
            ->where('user_id', '<>', $this->message->user_id)
            ->get();
        Log::info('Other user: ' . $other_users);

        $channels = [];
        foreach ($other_users as $user) {
            $channels[] = new PresenceChannel('Messenger.' . $user->id);
        }

        return $channels;
    }



    public function broadcastAs()
    {
        return 'new-message';
    }
}
