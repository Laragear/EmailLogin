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
    | Guards configuration
    |--------------------------------------------------------------------------
    |
    | Here is a simple guard configuration: where the email address is on the
    | request and the user model / object. Usually, all users have it on the
    | the "email" key attribute but you may change it in a per-guard basis.
    |
    */
    'guards' => [
        'web' => 'email'
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
        'view' => 'email-login::web.login',
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

    'minutes' => 5,

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
        'view' => 'email-login::mail.login',
    ],
];
