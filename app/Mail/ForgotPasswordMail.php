<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The string URL that will be attached to the reset password email.
     *
     * @var string
     */
    public $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(string $token, string $emailHash)
    {
        $this->resetUrl = \Config::get('app.client_url').'/reset-password/'.$token.'/'.$emailHash;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject('Trig: Forgot Password Link')
            ->view('emails.forgot-password');
    }
}
