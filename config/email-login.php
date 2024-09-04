<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default guard
    |--------------------------------------------------------------------------
    |
    | The default guard to retrieve the user and send a login email. When not
    | set, the application default will be used. On fresh installations, the
    | default guard is "web", which uses the DB Eloquent to retrieve users.
    |
    */

    'defaults' => [
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Login route & view
    |--------------------------------------------------------------------------
    |
    | This named route is linked in the email, which contains the view form to
    | log in the user. We won't log him in directly because some mail clients
    | will prefetch / preload the login link and may log him in by accident.
    |
    */

    'route' => [
        'name' => 'auth.email.login',
        'view' => 'laragear::email-login.web.login',
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    |
    | By default, there is no throttling to send the email login. Here you can
    | add a default throttling for all outgoing email logins. The "prefix" is
    | appended to the cache prefix, becoming "[email-login|throttle|{key}]".
    |
    */

    'throttle' => [
        'store' => null,
        'prefix' => 'throttle'
    ],

    /*
    |--------------------------------------------------------------------------
    | Link expiration
    |--------------------------------------------------------------------------
    |
    | When mailing the link, a signed URL will be generated with an expiration
    | time. You can control how many minutes to keep the link valid until its
    | detected in the included view form as "expired" and no longer working.
    |
    */

    'expiration' => 5,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | To avoid using the same email link more than once, a "login intent" for
    | the user in stored in the application cache, expiring at the same time
    | than the email link. If not set, it will use the application default.
    |
    */

    'cache' => [
        'store' => null,
        'prefix' => 'email-login'
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail driver
    |--------------------------------------------------------------------------
    |
    | This controls the mailer that sends the mail, which connection and queue
    | to use when sending it, and the mail view. When "null", these use the
    | app default. You may override these by using a closure at runtime.
    |
    */

    'mail' => [
        'mailer' => null,
        'connection' => null,
        'queue' => null,
        'view' => 'laragear::email-login.mail.login',
    ],
];
