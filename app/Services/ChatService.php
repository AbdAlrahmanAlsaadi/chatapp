<?php

namespace App\Services;

use App\Events\PrivateMessage;
use App\Events\PublicMessage;
use App\Events\UserStatusUpdated;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
class ChatService
{
    public function sendMessageWithMedia(array $data)
    {
        $message = new Message();
        $message->conversation_id = $data['conversation_id'];
        $message->user_id = auth()->id();
        $message->message = $data['message'] ?? null;
        $message->status = 'sent';

        // التحقق من نوع الوسائط وتخزينها إذا وجدت
        if (isset($data['media'])) {
            $destinationPath = 'uploads/media/';
            $media = $data['media'];
            $fileName = time() . '_' . $media->getClientOriginalName();

            // تحديد المسار بناءً على نوع الوسائط
            switch ($data['media_type']) {
                case 'image':
                    $destinationPath .= 'images';
                    break;
                case 'video':
                    $destinationPath .= 'videos';
                    break;
                case 'audio':
                    $destinationPath .= 'audio';
                    break;
                default:
                    throw new \Exception('Invalid media type.');
            }

            // رفع الملف وتحديث مسار الوسائط والنوع
            $media->move(public_path($destinationPath), $fileName);
            $message->media_path = $destinationPath . '/' . $fileName;
            $message->media_type = $data['media_type'];
        }

        $message->save();

        broadcast(new PrivateMessage($message))->toOthers();
        // broadcast(new PublicMessage($message))->toOthers();
        Log::channel('telegram')->info('Image has been uploaded', [
            'url' => url('/') . '/' . $destinationPath . '/' . $fileName
        ]);
        return $message;
    }

    public function createConversation(array $validatedData)
    {
        // التحقق من وجود محادثة سابقة بين المستخدمين
        $existingConversation = Conversation::where(function ($query) use ($validatedData) {
            $query->where('user1_id', Auth::id())
                  ->where('user2_id', $validatedData['user_id']);
        })->orWhere(function ($query) use ($validatedData) {
            $query->where('user1_id', $validatedData['user_id'])
                  ->where('user2_id', Auth::id());
        })->first();

        // إذا كانت المحادثة موجودة بالفعل، إرجاع null
        if ($existingConversation) {
            return null;
        }

        // إنشاء المحادثة الجديدة
        $conversation = Conversation::create([
            'user1_id' => Auth::id(),
            'user2_id' => $validatedData['user_id'],
        ]);

        return $conversation;
    }


    public function updateMessageStatus(int $messageId, string $status): bool
    {
        $validStatuses = ['sent', 'delivered', 'seen'];

        // التحقق من الحالة المرسلة
        if (!in_array($status, $validStatuses)) {
            return false; // الحالة غير صالحة
        }

        Message::where('id', $messageId)
            ->where('user_id', '!=', Auth::id()) // تأكد من أن المستخدم الحالي ليس هو من أرسل الرسالة
            ->whereIn('status', array_slice($validStatuses, 0, array_search($status, $validStatuses) + 1))
            ->update(['status' => $status]);

        return true;
    }
        public function deleteMessage(int $messageId): bool
{
    $message = Message::find($messageId);

    if (!$message) {
        return false; //
    }

    // التأكد من أن المستخدم الحالي هو مرسل الرسالة
    if ($message->user_id !== Auth::id()) {
        return false; // المستخدم ليس صاحب الرسالة
    }

    return $message->delete();
}
public function getUserConversations()
{
    $userId = Auth::id();

    // جلب كافة المحادثات الخاصة بالمستخدم الحالي مع معلومات المستخدمين الآخرين في كل محادثة
    $conversations = Conversation::where('user1_id', $userId)
        ->orWhere('user2_id', $userId)
        ->with(['user1.stories', 'user2.stories','lastMessage']) // إضافة معلومات المستخدمين المشاركين في المحادثة
        ->get();

    return $conversations;
}
    public function getMessagesByConversationId(int $conversationId, int $offset = 0, int $limit = 30)
    {
        $userId = Auth::id();
        $conversation = Conversation::find($conversationId);

        if (!$conversation || (!$conversation->user1_id === $userId && !$conversation->user2_id === $userId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc') // ترتيب الرسائل من الأحدث إلى الأقدم
            ->offset($offset)
            ->limit($limit)->with('reactions')
            ->get();
    }
    public function updateUserStatus(bool $isOnline): void
{
    $userId = Auth::id();

    if ($userId) {
        $user = User::find($userId);

        $user->update([
            'is_online' => $isOnline,
            'last_seen_at' => $isOnline ? null : Carbon::now(),
        ]);

        broadcast(new UserStatusUpdated($user));
    }}

    public function searchUser(string $query)
    {
        
        return User::where('user_name', 'LIKE', "%{$query}%")

                    ->get();
    }
}
