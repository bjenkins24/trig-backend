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
     * The string URL that will be attached to the reset pwd email.
     *
     * @var string
     */
    public $pwdResetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(string $pwdResetToken)
    {
        $this->pwdResetUrl = config('app.client_url').'/reset-password/'.$pwdResetToken;
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
