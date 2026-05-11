<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    // ✅ Remove type hints from properties
    public $subscriberEmail;
    public $token;

    public function __construct($email, $token)
    {
        $this->subscriberEmail = $email;
        $this->token = $token;
    }

    public function build()
    {
        return $this->subject('Welcome to Workason Newsletter! 🎉')
                    ->view('emails.newsletter_welcome');
    }
}