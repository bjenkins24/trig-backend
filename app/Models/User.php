<?php

namespace App\Models;

use App\Mail\ForgotPasswordMail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements CanResetPasswordContract
{
    use Notifiable;
    use HasApiTokens;
    use CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'terms_of_service_accepted_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'terms_of_service_accepted_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get user's full name.
     *
     * @return string
     */
    public function name()
    {
        if (! $this->first_name || ! $this->last_name) {
            $email = explode('@', $this->email);

            return sprintf(
                '%s (at) %s',
                Arr::get($email, '0'),
                Arr::get($email, '1')
            );
        }

        return $this->first_name.' '.$this->last_name;
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     */
    public function sendPasswordResetNotification($token)
    {
        $userFullName = $this->name();

        // and set the name prop, because Mail needs it
        $this->name = $userFullName;

        // send the email
        Mail::to($this)->send(new ForgotPasswordMail($token));
    }
}
