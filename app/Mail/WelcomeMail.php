<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this
            ->subject('Welcome to Trig!')
            ->view('emails.welcome');
    }
}
