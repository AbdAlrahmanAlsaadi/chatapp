<?php
namespace App\Services;

use App\Models\Conversation;
use App\Models\Reaction;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class ReactionService
{
    public function addReaction(array $data)
    {
        $userId = Auth::id();

        // العثور على الرسالة المرتبطة بالتفاعل
        $message = Message::find($data['message_id']);

        if (!$message) {
            throw new \Exception('Message not found.');
        }

        // العثور على المحادثة المرتبطة بالرسالة
        $conversation = conversation::find($message->conversation_id);

        // التحقق من أن المستخدم جزء من المحادثة
        if ($conversation->user1_id !== $userId && $conversation->user2_id !== $userId) {
            throw new \Exception('You are not a participant in this conversation.');
        }

        // إضافة التفاعل بعد التحقق
        $reaction = Reaction::create([
            'user_id' => $userId,
            'message_id' => $data['message_id'],
            'reaction_type' => $data['reaction_type'],
        ]);

        return $reaction;
    }

    public function removeReaction(array $data)
    {
        $userId = Auth::id();

        // العثور على التفاعل الذي نريد حذفه
        $reaction = Reaction::where('user_id', $userId)
                            ->where('message_id', $data['message_id'])
                            ->where('reaction_type', $data['reaction_type']) // في حالة أنك تريد حذف نوع معين من التفاعل
                            ->first();

        if (!$reaction) {
            throw new \Exception('Reaction not found.');
        }

        // العثور على الرسالة المرتبطة بالتفاعل
        $message = Message::find($data['message_id']);

        if (!$message) {
            throw new \Exception('Message not found.');
        }

        // العثور على المحادثة المرتبطة بالرسالة
        $conversation = Conversation::find($message->conversation_id);

        // التحقق من أن المستخدم جزء من المحادثة
        if ($conversation->user1_id !== $userId && $conversation->user2_id !== $userId) {
            throw new \Exception('You are not a participant in this conversation.');
        }

        // حذف التفاعل
        $reaction->delete();

        return response()->json(['message' => 'Reaction removed successfully.'], 200);
    }

    public function getReactions()
    {
        return Reaction::get();
    }
}
