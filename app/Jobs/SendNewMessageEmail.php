<?php

namespace App\Jobs;

use App\Mail\NewMessageNotification;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewMessageEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    public Message $message;
    public int $receiverId;

    public function __construct(Message $message, int $receiverId)
    {
        $this->message    = $message;
        $this->receiverId = $receiverId;
    }

  public function handle(): void
{
    Log::info('SendNewMessageEmail job started', [
        'receiver_id' => $this->receiverId,
        'message_id'  => $this->message->id,
    ]);

    $receiver = User::find($this->receiverId);
    $sender   = User::find($this->message->user_id);

    if (!$receiver || !$sender) {
        Log::error('Receiver or sender not found');
        return;
    }

    // Handle users who signed up via Google (have first_name/last_name but no name)
    $receiverName = $receiver->name 
        ?? trim(($receiver->first_name ?? '') . ' ' . ($receiver->last_name ?? '')) 
        ?: 'User';

    $senderName = $sender->name 
        ?? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) 
        ?: 'User';

    try {
        Mail::to($receiver->email)->send(
            new NewMessageNotification(
                $this->message,
                $senderName,
                $receiverName
            )
        );
        Log::info('Email sent successfully to ' . $receiver->email);
    } catch (\Exception $e) {
        Log::error('Mail failed: ' . $e->getMessage());
        throw $e;
    }
}
}