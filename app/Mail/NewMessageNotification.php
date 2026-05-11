<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Message $chatMessage;  // renamed from $message
    public string $senderName;
    public string $receiverName;

    public function __construct(Message $chatMessage, string $senderName, string $receiverName)
    {
        $this->chatMessage  = $chatMessage;
        $this->senderName   = $senderName;
        $this->receiverName = $receiverName;
    }

    public function build(): self
    {
        return $this
            ->subject("💬 New message from {$this->senderName}")
            ->view('emails.new-message');
    }
}