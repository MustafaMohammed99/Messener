<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDelated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $users;
    public $id_message;
    public $id_conversation;

    /**
     * Create a new event instance.
     * @param users  users have message will deleted الاشخاص الذين وصلت الرسالة لديهم سيتم حذف الرسالة من عندهم
     */
    public function __construct($users, $id_message, $id_conversation)
    {
        $this->users = $users;
        $this->id_message = $id_message;
        $this->id_conversation = $id_conversation;
    }


    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {

        $channels = [];
        foreach ($this->users as $user) {
            $channels[] = new PresenceChannel('Messenger.' . $user->id);
        }

        return $channels;
    }



    public function broadcastAs()
    {
        return 'message-deleted';
    }
}
