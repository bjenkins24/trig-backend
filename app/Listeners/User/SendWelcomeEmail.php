<?php

namespace App\Listeners\User;

use App\Events\User\AccountCreated;
use App\Mail\WelcomeMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(AccountCreated $event)
    {
        $userFullName = $event->user->name();
        // and set the name prop, because Mail needs it
        $event->user->name = $userFullName;
        Mail::to($event->user)->send(new WelcomeMail());
    }
}
