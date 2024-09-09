# Email Login

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/email-login.svg)](https://packagist.org/packages/laragear/email-login)
[![Latest stable test run](https://github.com/Laragear/EmailLogin/workflows/Tests/badge.svg)](https://github.com/Laragear/EmailLogin/actions)
[![Codecov coverage](https://codecov.io/gh/Laragear/EmailLogin/graph/badge.svg?token=Nfr8cAlFvC)](https://codecov.io/gh/Laragear/EmailLogin)
[![Maintainability](https://api.codeclimate.com/v1/badges/3ffd5af2566998e5897f/maintainability)](https://codeclimate.com/github/Laragear/EmailLogin/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_EmailLogin&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_EmailLogin)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/11.x/octane#introduction)

Authenticate users through their email in 1 minute.

```html
<form method="post" action="/auth/email/send">
    @csrf
    <input type="email" name="email" placeholder="me@email.com">
    <button type="submit">Log in</button>
</form>
```

## Keep this package free

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FEmailLogin&hashtags=PHP,Laravel)**
## Installation

Then call Composer to retrieve the package.

```bash
composer require laragear/email-login
```

## 1 minute quickstart

Email Mail is very simple to install: put the email of the user you want to authenticate in a form, and an email will be sent to him with a single-time link to authenticate.

First, install the configuration file and the base the controllers using the `email-login:install` Artisan command.

```shell
php artisan email-login:install
```

After that, ensure you register the routes that the login email will use for authentication using the included route registrar helper at `Laragear\EmailLogin\Http\Routes` class. 

```php
use Illuminate\Routing\Route;
use Laragear\EmailLogin\Http\Routes as EmailLoginRoutes;

Route::view('welcome');

// Register the default Mail Login routes
EmailLoginRoutes::register();
```

> [!TIP]
> 
> You may change the route path for the email login as an argument, additionally to the controller.
> 
> ```php
> use Laragear\EmailLogin\Http\Routes as EmailLoginRoutes;
> 
> EmailLoginRoutes::register(
>     send: '/send-email-here',
>     login: '/login-from-email-here',
>     controller: 'App/Http/Controllers/MyEmailLoginController',
> );
> ```

Finally, add a "login" box that receives the user email by making a `POST` to `auth/email`, anywhere in your application.

```html
<form method="post" action="/auth/email/send">
    @csrf
    <input type="email" name="email" placeholder="me@email.com">
    <button type="submit">Log in</button>
</form>
```

That's it, your user is ready to log in with its email.

This package will handle the whole logic for you, but you can always go full manual with your own routes and controllers.

## Sending the login email

To implement the login email manually, you need to capture the user credentials from the form submission. The `EmailLoginRequest` does most of the heavy lifting for you. 

If you're using the defaults that come with Laravel, the request automatically validates the email. You only need to return the `sendAndBack()` method to redirect the user back to the form. 

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->sendAndBack();
});
```

You can also use `send()` and `back()` separately if you need to do something before sending the email, and use the `validate()` method if you want to expand on the email validation rules.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    $email->validate([
        'email' => 'required|email:rfc,dns'
    ]);
    
    $email->send();
    
    session()->flash('message', 'Email sent successfully!');

    return back();
});
```

Both `sendAndBack()` and `send()` return `true` if the user with the credentials is found and the email is sent (or queued to be sent), and `false` if the user doesn't exist. Some apps will be fine by obfuscating the user existence, but some may want to show if the user doesn't exist.

```php
if ($email->send()) {
    session()->flash('message', 'Email sent successfully!');
} else {
    throw ValidationException::withMessages([
        'email' => 'The user with the email does not exist'
    ]);
}
```

### Custom credentials

When sending an Email Login, the validated data is used to find the user to be authenticated through the User Provider of the Guard. For example, if you validate the `username` key, only that will be used to find the user and send the email.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    $email->validate([
        'username' => 'required|string|exists:users'
    ]);
    
    return $email->sendAndBack();
});
```

You can override the credentials to find the user using the `withCredentials()` method with a list of the keys in the request input that should be used as credentials. The list may be different from the validated request input.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    $email->validate([
        // ...
    ]);
    
    return $email->withCredentials(['username', 'mail'])->sendAndBack();
});
```

Keys can also be callbacks that receive the query to find the User.

```php
$email->withCredentials([
    'mail',
    fn($query) => $query->where('is_human', '>', 0.5)
]);
```

Alternatively, if you issue key-value pair, the value of the key will be used as a credential value.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    $email->validate([
        'email' => 'required|email'
    ]);
    
    return $email->withCredentials([
        'mail' => $email->email,
        'banned_at' => null,
        fn($query) => $query->where('standing', '>', 0.5)
    ])->sendAndBack();
});
```

### Login expiration

The link to login sent in the email has an expiration time, which by default is 5 minutes. You can change this globally through the [configuration](#link-expiration) or at runtime using the `withExpiration()` with either the amount of minutes, a `DateTimeInterface` instance, or a string to be passed to [`strtotime()`](https://www.php.net/manual/function.strtotime.php).

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withExpiration(10)->sendAndBack();
});
```

### Custom remember key

If your request has the `remember` key, and it's _truthy_, the user will be remembered into the application when he logs in. If the key is different, you may set a string with the key name through the `withRemember()` method.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withRemember('remember_me')->sendAndBack();
});
```

Alternatively, issuing anything else will be used as the condition, like a boolean or a callback.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withRemember($email->boolean('remember_me'))->sendAndBack();
});
```

### Specifying the guard

By default, the Email Login assumes the user will authenticate using the default guard, which in most _vanilla_ Laravel applications is `web`. You may want to change the [default guard in the configuration](#guard), or change it at runtime using `withGuard()`:

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withGuard('admin')->sendAndBack();
});
```

### Email URL link

You may change the URL where the Email Login will point to through the [configuration](#route-name--view), or at runtime using the `withPath()`, `withAction()`, and `withRoute()` methods. You may set also parameters using an array as a second argument, if you need to.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withRoute('auth.email.login', ['is_cool' => true])->sendAndBack();
});
```

You may also only append query parameters to the default URL set in the configuration using `withParameters()` method.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withParameters(['is_cool' => true])->sendAndBack();
});
```

> [!WARNING]
>
> The route **must** exist. This route should show a form to login, **not** login the user immediately. See [Login in from a mail](#login-in-from-a-mail).

### Customizing the Mailable

The most basic approach to use your own [Mailable](https://laravel.com/docs/11.x/mail#generating-mailables) class is to set it through the `withMailable()` method, either as a class name (instanced by the Container) or as a Mailable instance.

```php
use App\Mails\MyLoginMailable;
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withMailable(MyLoginMailable::class)->sendAndBack();
});
```

Alternatively, you may want to use callback to customize the included Mailable instance. The callback receives the `LoginEmail` mailable. Inside the callback you're free to modify the mailable to your liking, like changing the view or the destination, or even return a new Mailable.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Mails\LoginEmail;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withMailable(function (LoginEmail $mailable) {
        $mailable->view('my-login-email', ['theme' => 'blue']);
        
        $mailable->subject('Login to this awesome app');
    })->sendAndBack();
});
```

### Opaque throttling

If you want to throttle sending the email _opaquely_, just use the `withThrottle()` method with the amount of seconds. During that time, the email will not be sent. This is great to avoid a massive amount of emails. 

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    return $email->withThrottle(30)->sendAndReturnBack();
});
```

The throttling uses the same cache used to store the email login intent, and the request fingerprint (IP) by default. You may change the cache store to use as second name, and even the key to use as throttler as third argument.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

Route::post('/auth/email/send', function (EmailLoginRequest $email) {
    $key = strtolower($email->input('email'));

    return $email->withThrottle(30, 'redis', $key)->sendAndReturnBack();
});
```

### Adding metadata

You can save data that's valid only for the login attempt using the `withMetadata()` method. You may set here an array of keys and values that you can [later retrieve](#retrieving-metadata) when the login is successful.

```php
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;

// Send the email and save the metadata internally.
Route::post('/auth/email/send', function (EmailLoginRequest $request) {
    return $request
        ->withMetadata(['is_cool' => true])
        ->sendAndReturnBack();
});

// Show the login form with the metadata.
Route::get('/auth/email/login', function (LoginByEmailRequest $request) {
    return view('laragear::email-login.web.login', [
        'is_cool' => $request->metadata('is_cool')
    ]);
});
```

> [!TIP]
> 
> The metadata is not transmitted in the email link, but stored as part of the Email Login Intent inside your application cache.


## Login in from a Mail

The login procedure from an email must be done in two controller actions: one showing a form, and another authenticating the user. Both of these routes should use the `guest` middleware to avoid being hit by an authenticated user.

> [!WARNING]
> 
> The Log In must be done in two controller actions because **some email clients and servers will preload, cache and/or prefetch the login link**. While this is usually done to accelerate navigation or filter malicious sites, this will accidentally log in the user outside its device, and render subsequent login attempts unsuccessful. 
> 
> To avoid this accidental authentication, make a route that shows a form to login, and another to authenticate the user.

Use the `LoginByEmailRequest` to return the view with the form to login, and to log in the user, on both users.

- When the login is invalid or expired, an HTTP 419 (Expired) error is shown to the user instead of the view. Otherwise, you may use the included `laragear::email-login.web.login` view to show the form.
- When receiving the login form submission, the user will be automatically logged in.

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;

Route::middleware('guest')->group(function () {
    // Show the form to log in. 
    Route::get('/auth/login/mail', function (LoginByEmailRequest $request) {
        return view('laragear::email-login.web.login')
    })->name('login.mail');
    
    // User logged in automatically, show him the dashboard. 
    Route::post('/auth/login/mail', function (LoginByEmailRequest $request) {
        return $request->toIntended()
    });
})
```

### Retrieving metadata

If you have [set metadata before sending the email](#adding-metadata), you can retrieve it using the `metadata()` method along with the key in `dot.notation`, and optionally a default value if it's not set.

```php
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Illuminate\Support\Facades\Route;

Route::get('auth/login/mail', function (LoginByEmailRequest $request) {
    return view('laragear::email-login.web.login', [
        'is_cool' => $request->metadata('is_cool');
    ]);
});
```

## Email Login Broker

If you want a more _manual_ way to log in the user, use the `EmailLoginBroker`, which is what the Form Request helpers use behind the scenes.

To create an email login intent, use the `create()`. It requires the authentication guard, the user ID, and an expiration time. It returns a random token that should be used to transmit via Email.

```php
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\EmailLoginBroker;use Laragear\EmailLogin\Mails\LoginEmail;

Route::post('/send-login-email', function (Request $request, EmailLoginBroker $broker) {
    $request->validate([
        'email' => 'required|email'
    ]);
    
    // Find the user by the email
    $user = User::where('email', $request->email)->first();
    
    // Send the email if the user exists.
    if ($user) {
        $url = url('/login-by-email', [
            'token' => $broker->create('web', $user->id),
            'guard' => 'web',
        ]);
        
        // Send the email with the url to the user.
        LoginEmail::make($user, $url)->to($request->email)->send();
    }
    
    session()->flash('message', 'Login email sent successfully!');
    
    return back();
});
```

After the user is redirected to your email login form, use the `get()` method with the token to retrieve the `EmailLoginIntent`.

```php
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;use Illuminate\Support\Facades\Route;
use Laragear\EmailLogin\EmailLoginBroker;use Laragear\EmailLogin\Mails\LoginEmail;

Route::get('/login-by-email', function (Request $request, EmailLoginBroker $broker) {
    // If the intent exists, show him the login form.
    if ($broker->get($request->query('token'))) {
        return view('my-email-login-view');
    }
    
    // If it doesn't exist, redirect the user back to the initial login.
    return redirect('send-login-email');
});
```

Once the form submission is received, use the `pull()` method to remove the intent from the cache store and log in the user using the `EmailLoginIntent` instance data.

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Laragear\EmailLogin\EmailLoginBroker;

Route::post('/login-by-email', function (Request $request, EmailLoginBroker $broker) {
    $intent = $broker->pull($request->query('token'));
    
    // If the intent doesn't exist, bail out.
    if (!$intent) {
        return redirect('send-login-email');
    }

    // Log in the user using the intent data.
    Auth::guard($intent->guard)->loginUsingId($intent->id, $intent->remember);

    // Regenerate the session for security.
    $request->session()->regenerate();

    return redirect('/dashboard');
});
```

## Custom token string

When generating the email login token, a random ULID will be generated. You may change the default generator by setting a callback that receives the `EmailLoginIntent` and returns a (hopefully very) random string. You may do this in your `AppServiceProvider::register()`.

```php
use Illuminate\Support\Str;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginIntent;

public function register()
{
    EmailLoginBroker::$tokenGenerator = function (EmailLoginIntent $intent) {
        return Str::random(128);
    };
}
```

## Advanced Configuration

Mail Login was made to work out-of-the-box, but you may override the configuration by simply publishing the config file if you're not using Laravel's defaults.

```shell
php artisan vendor:publish --provider="Laragear\EmailLogin\EmailLoginServiceProvider" --tag="config"
```

After that, you will receive the `config/email-login.php` config file with an array like this:

```php
return [
    'guard' => null,
    'route' => [
        'name' => 'login.mail',
        'view' => 'laragear::email-login.web.login',
    ],
    'throttle' => [
        'store' => null,
        'prefix' => 'throttle'
    ],
    'expiration' => 5,
    'cache' => [
        'store' => null,
        'prefix' => 'email-login'
    ],
    'mail' => [
        'mailer' => null,
        'connection' => null,
        'queue' => null,
        'view' => 'laragear::email-login.mail.login',
    ],
];
```

### Guard

```php
return [
    'guard' => null,
];
```

The default Authentication Guard to use. When `null`, it fall backs to the application default, which is usually `web`. The User Provider set for the guard is used to find the user. 

### Route name & View

```php
return [
    'route' => [
        'name' => 'login.mail',
        'view' => 'laragear::email-login.web.login',
    ],
];
```

This named route is linked in the email, which contains the view form to log in the user. 

### Throttle

```php
return [
    'throttle' => [
        'store' => null,
        'prefix' => 'throttle'
    ],
];
```

When [throttling the email](#opaque-throttling), this configuration will be used to set which cache store and prefix to use.

### Cache

```php
return [
    'cache' => [
        'store' => null,
        'prefix' => 'email-login'
    ],
];
```

Email Login intents are saved into the cache for a given duration. Here you can change the cache store and prefix used to store them. When `null`, it will use the default application store.

### Link expiration

```php
return [
    'expiration' => 5,
];
```

When mailing the link, a signed URL will be generated with an expiration time. You can control how many minutes to keep the link valid until it is expunged by the cache store.

### Mail driver

```php
return [
    'mail' => [
        'mailer' => null,
        'connection' => null,
        'queue' => null,
        'markdown' => 'laragear::email-login.mail.login',
    ],
];
```

This specifies which mail driver to use to send the login email, and the queue connection and name that will receive it. When `null`, it will fall back to the application default, which is usually `smtp`.

This also sets the default view to use to create the email, which [uses Markdown](https://laravel.com/docs/11.x/mail#markdown-mailables).

## Laravel Octane Compatibility

* There are no singletons using a stale application instance.
* There are no singletons using a stale config instance.
* There are no singletons using a stale request instance.
* Two static property accessible to write are
    * `LoginByMailRequest::$destroyOnRegeneration`
    * `EmailLoginBroker::$tokenGenerator`

There should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

### Blocking authentication after the email is sent.

Once the Login Email is sent to the user, the `LoginByEmailRequest` won't be able to block the authentication procedure since it does not check for anything more than a valid Email Login intent.

For example, if a user is banned _after_ the login email is sent, the user will still be able to authenticate.

To avoid this, extend the `LoginByEmailRequest` and modify the `login()` method to add further checks on a manually retrieved user. Then use this new class on your login controller of choice.

```php
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;

class MyLoginRequest extends LoginByEmailRequest
{
    /**
     * Proceed to log in the user after a successful form submission.
     */
    protected function login(StatefulGuard $guard, mixed $id, bool $remember): void
    {
        $user = User::whereNull('banned_at')->find($id);
        
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'The user for this email has been banned.'
            ]);
        }
        
        $guard->login($user, $remember);
    }
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

Laravel is a Trademark of Taylor Otwell. Copyright © 2011-2024 Laravel LLC.
