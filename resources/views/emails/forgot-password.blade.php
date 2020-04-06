@extends('layouts.email')

@section('title', 'Reset Password')

@section('content')
    <p>
        Someone requested that the password for your Trig account be reset. Click the button below to reset it.
    </p>
    
    <p style="text-align: center">
        <a href="{{ $resetUrl }}" style="display: inline-block; font-weight: 500; padding: 16px 32px; margin: 32px auto; background: #009E8F; color: #F5F5F5; text-decoration: none; text-align: center; border-radius: 4px;">
            Reset Password
        </a>
    </p>

    <p>
        If you didn’t make this request, then you can safely ignore this email. 
        Only somone with access to your email account can reset your password.
    </p>
@endsection