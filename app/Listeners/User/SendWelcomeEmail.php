<?php

namespace App\Listeners\User;

use App\Events\User\AccountCreated;
use App\Jobs\SendMail;
use App\Mail\WelcomeMail;
use App\Modules\User\UserService;

class SendWelcomeEmail
{
    /**
     * Handle the event.
     */
    public function handle(AccountCreated $event): void
    {
        $userFullName = app(UserService::class)->getName($event->user);
        // Set the name prop, because Mail needs it
        $event->user->name = $userFullName;
        SendMail::dispatch($event->user, new WelcomeMail());
    }
}
