<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChatOnlyResource;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Jobs\SendNewMessageEmail;

class ChatController extends Controller
{
    // ✅ Restored
    public function index()
    {
        $chats = Chat::with(["Host", "ChatUser", "messages"])
            ->where("user1", auth()->user()->id)
            ->orWhere("user2", auth()->user()->id)
            ->get();
        return okResponse("Chats fetched", ChatOnlyResource::collection($chats));
    }

    // ✅ Restored
    public function show($id)
    {
        $chat = Chat::with(["Host", "ChatUser", "messages"])
            ->where("id", $id)
            ->first();

        if (!$chat)
            return errorResponse("Chat not found", [], 404);

        if ($chat->user1 != auth()->user()->id && $chat->user2 != auth()->user()->id)
            return errorResponse("Unauthorized", [], 403);

        return okResponse("Chat fetched", new ChatResource($chat));
    }

    // ✅ Updated to support file_url
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message'     => 'nullable|string',
            'receiver_id' => 'required|integer',
            'file_url'    => 'nullable|string',
        ]);

        $userId = auth()->user()->id;
        $receiverId = $request->receiver_id;

        $chat = Chat::where(function ($q) use ($userId, $receiverId) {
            $q->where('user1', $userId)->where('user2', $receiverId);
        })->orWhere(function ($q) use ($userId, $receiverId) {
            $q->where('user1', $receiverId)->where('user2', $userId);
        })->first();

        if (!$chat) {
            $chat = Chat::create([
                'user1' => $userId,
                'user2' => $receiverId,
            ]);
        }

        $message = Message::create([
            'message'  => $request->message ?? '',
            'file_url' => $request->file_url ?? null,
            'user_id'  => $userId,
            'chat_id'  => $chat->id,
        ]);

        try {
            broadcast(new \App\Events\MessageSent($message))->toOthers();
        } catch (\Exception $e) {
            \Log::warning('Broadcast failed (WebSocket server may be down): ' . $e->getMessage());
        }

        SendNewMessageEmail::dispatch($message, $receiverId)
            ->delay(now()->addSeconds(30));

        $chat->load(["Host", "ChatUser", "messages"]);
        return okResponse("Message sent", new ChatResource($chat));
    }
}