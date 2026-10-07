<?php

namespace App\Mail;

use App\Models\SmartStartRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SmartStartSelectionMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $freelancer;
    public User $employer;
    public SmartStartRequest $smartStart;

    public function __construct(User $freelancer, User $employer, SmartStartRequest $smartStart)
    {
        $this->freelancer  = $freelancer;
        $this->employer    = $employer;
        $this->smartStart  = $smartStart;
    }

    public function build(): self
    {
        return $this->subject('You\'ve Been Selected for a Project on Workason!')
            ->view('emails.smartstart-selected');
    }
}
