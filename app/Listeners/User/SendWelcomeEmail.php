<?php

namespace App\Listeners\User;

use App\Events\User\AccountCreated;
use App\Mail\WelcomeMail;
use App\Modules\User\UserService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeEmail implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(AccountCreated $event)
    {
        $userFullName = app(UserService::class)->getName($event->user);
        // and set the name prop, because Mail needs it
        $event->user->name = $userFullName;
        \Mail::to($event->user)->send(new WelcomeMail());
    }
}
