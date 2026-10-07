<?php

namespace App\Mail;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AffiliateWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public Affiliate $affiliate;

    public function __construct(User $user, Affiliate $affiliate)
    {
        $this->user      = $user;
        $this->affiliate = $affiliate;
    }

    public function build(): self
    {
        return $this->subject('Welcome to the Workason Affiliate Programme!')
            ->view('emails.affiliate-welcome');
    }
}
