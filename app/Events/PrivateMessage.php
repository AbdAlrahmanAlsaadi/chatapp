<?php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class PrivateMessage implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        // بث إلى القناة الخاصة فقط
        return new PrivateChannel('chat.' . $this->message->conversation_id);
    }

    public function broadcastWith()
    {
        // توفير البيانات التي سيتم بثها مع الرسالة
        return [
            'id' => $this->message->id,
            'message' => $this->message->message,
            'status' => $this->message->status,
            'updated_at' => $this->message->updated_at,
            'created_at' => $this->message->created_at,
            'user_id' => $this->message->user_id,
            'media_path'=>$this->message->media_path,
            'media_type'=>$this->message->media_type,

        ];
    }
}

