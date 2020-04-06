<!DOCTYPE html>
<html>
<head>
</head>

<body style="font-family: Hero New, verdana, sans-serif; line-height: 1.8; font-size: 16px; background: #F5F5F5; color: #2c2929; letter-spacing: 0.3px">
    <div style="height: 64px"></div>
    <div style="max-width: 744px; margin: 0 auto; padding: 32px 0;">
        <a href="https://trytrig.com">
            <img src="{{ asset('images/logo.png') }}" alt="Trig" height="32" style="height: 32px" />
        </a>
    </div>
    <div style="border-radius: 4px; background: #FFFFFF; padding: 32px; max-width: 680px; margin: 0 auto;">
        <h1 style="font-size: 34px; text-align: center; margin: 0 0 32px 0; font-weight: 500; letter-spacing: 0.5px">@yield('title')</h1>
        @yield('content')
    </div>
    <div style="height: 64px"></div>
</body>
</html>