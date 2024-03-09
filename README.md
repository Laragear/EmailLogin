# Email Login

[![Latest stable test run](https://github.com/Laragear/EmailLogin/workflows/Tests/badge.svg)](https://github.com/Laragear/EmailLogin/actions)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/11.x/octane#introduction)

Authenticate users through their email in 1 minute.

```html
<form method="post" action="/login/email/send">
    @csrf
    <input type="email" name="email" placeholder="me@email.com">
    <button type="submit">Log in</button>
</form>
```

## Thanks to you!

![](.github/assets/supported.png)

You're reading this because you're supporting me and this package. Your support allows me to keep this package free, up-to-date and maintainable. Thanks! I mean it!

## Installation

You can install the package via Composer. Open your `composer.json` and point the location of the private repository under the `repositories` key.

```json
{
    // ...
    "repositories": [
        {
            "type": "vcs",
            "name": "laragear/email-login",
            "url": "https://github.com/laragear/emaillogin.git"
        }
    ],
}
```

Then call Composer to retrieve the package.

```bash
composer require laragear/email-login
```

You will be prompted for a personal access token. If you don't have one, follow the instructions or [create one here](https://github.com/settings/tokens/new?scopes=repo). It takes just seconds.

> [!NOTE]
> 
> You can find more information about in [this article](https://darkghosthunter.medium.com/php-use-your-private-repository-in-composer-without-ssh-keys-da9541439f59).

## 1 minute quickstart

Email Mail is very simple to install: put the email of the user you want to authenticate in a form, and a mail will be sent to him with a link to authenticate.

First, install the configuration file, the controllers and the migration file using the `mail-login:install` Artisan command.

```shell
php artisan email-login:install
```

After that, ensure you register the routes that the login email will use for authentication using the included route registrar helper at `\Laragear\EmailLogin\Http\Routes`.

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
> EmailLoginRoutes::register(
>     send: 'auth/email/send',
>     login: 'auth/email/login',
>     controller: 'App/Http/Controllers/MyEmailLoginController',
> );
> ```

Finally, add a "login" box that receives the user email by making a `POST` to `auth/email/send`, anywhere in your application.

```html
<form method="post" action="/auth/email/send">
    @csrf
    <input type="email" name="email" placeholder="me@email.com">
    <button type="submit">Log in</button>
</form>
```

This package will handle the whole logic for you, but you can always go manual.

> [!WARNING]
> 
> Ensure the "email" input key is the same key for the User email. Since it's passed has its credential, it will be used to find the User. For example, passing "email_address" won't work, since the User model doesn't have the "email_address" attribute. [This can be changed](#guard).

## Sending the login email

To send an email manually, use the `EmailLoginRequest` request in your controller action.

```php
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

public function send(EmailLoginRequest $request)
{
    return $request->send();
}
```

The `send()` method does the magic of validating the email in the request, sending the email, and returning a redirection back.

### Custom credentials

When sending an Email Login, the email will be used to find the user to authenticate. Will this will suffice for most application, you may also add additional credentials for the authentication attempt in the `withCredentials()` method. For example, we can include a callback to change the query to find the user.

```php
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;

public function send(EmailLoginRequest $request)
{
    return $request->withCredentials([
        fn($query) => $query->whereNull('banned_at')
    ])->send();
}
```

### Custom remember key

You can change _where_ the "remember" key is in the request with `rememberKey()`. This is also sent along with the Email Link to let users be logged in for extended periods of time on the device they open the email.

```php
$request->rememberKey('remember_me_in_this_device')->send();
```

### Email validation rules

By default, the request will validate if the email key is present. If it doesn't, a validation exception will be thrown automatically by Laravel.

Once the Request reaches the controller action, you may run additional validation rules on send to further [verify the email](https://laravel.com/docs/11.x/validation#rule-email) using an array, [Validation Rule](https://laravel.com/docs/11.x/validation#using-rule-objects) or a string. These rules will be applied automatically to the email key.

```php
$request->send(rules: 'email:rfc,dns');
``` 

> [!TIP]
> 
> If you need for additional or deeper rules, you can always use `$request-validate(...)` before you send the email.

### Specifying the guard

By default, it assumes the user will authenticate using the default guard, which in most _vanilla_ Laravel applications is `web`. You may want to change the default guard in the configuration, or change it at runtime using `guard()`:

```php
$request->guard('admins')->send();
```

### Email URL link

You may change the URL where the Email Login will point to at runtime using the `toRoute()` method. 

```php
$request->toRoute('auth.email.form', ['is_cool' => true]);
```

You may also only set the query parameters alone using `query()`.

```php
$request->withQuery(['is_cool' => true]);
```

> [!WARNING]
>
> The route **must** exist. This route should show a form to login, **not** login the user immediately. See [Login in from a mail](#login-in-from-a-mail).

### Modifying the Mailable

The most basic approach to use your own Mailable class is to set it through the `mailable()` method.

```php
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use App\Mails\MyLoginMailable;

public function send(EmailLoginRequest $request)
{
    return $request->mailable(MyLoginMailable::class)->send();
}
```

Alternatively, you may want to use callback to customize the email with a callback that receives the `LoginEmail` mailable. Inside the callback you're free to modify the mailable to your liking, like changing the view, or return your own mailable class.

```php
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Mails\LoginEmail;
use App\Mails\MyLoginMailable;

public function send(EmailLoginRequest $request)
{
    return $request->mailable(function (LoginEmail $mailable) {
        $mailable->view('my-login-email', ['theme' => 'blue']);
        
        $mailable->subject('Login to this awesome app');
    })->send();
}
```

## Login in from a Mail

The login procedure from an email must be done in two controllers actions: one showing a form, and another authenticating the user.

This must be done in two controllers because some email clients will **preload, cache and/or prefetch the login link**. While this is usually done to accelerate navigation or filter malicious links, this will accidentally log in the user outside its device.

To avoid this make a route that shows a form to login, and another to authenticate the user. The `mail-login::web-login` will take care to show the form, while the `LoginByEmailRequest` will authenticate the user.

Both of these routes should be `signed` to avoid tampering with the query parameters, and should share the same path.

```php
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Illuminate\Support\Facades\Route;

Route::get('login/mail', fn () => view('mail-login::web.login'))
    ->middleware(['guest', 'signed'])
    ->name('login.mail');

Route::post('login/mail', fn (LoginByEmailRequest $request) => $request->redirect('/dashboard'))
    ->middleware(['guest', 'signed']);
```

If you don't want to use the `LoginByEmailRequest` class, you may log in the user manually. Is also recommended to use the `EmailLoginBroker` to avoid the reuse of the login link.

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Laragear\EmailLogin\EmailLoginBroker;

Route::get('login/mail', fn () => view('mail-login::web.login'))
    ->middleware(['guest', 'signed'])
    ->name('login.mail');

Route::post('login/mail', function (Request $request, EmailLoginBroker $broker) {
    $request->validate(['email' => 'required|email']);

    Auth::loginUsingId($broker->retrieve('web', $request->email));

    $request->session()->regenerate();

    return redirect('/dashboard');
})->middleware(['guest', 'signed']);
```

## Throttling the email sent

Usually you will want to let throttle the email form for a couple of minutes. You're encouraged to use [Laravel's included Request Throttler](https://laravel.com/docs/11.x/routing#attaching-rate-limiters-to-routes). Since the `Routes::register()` returns the Route for sending the email, you're just a line away.

```php
use Illuminate\Routing\Route;
use Laragear\EmailLogin\Http\Routes as EmailLoginRoutes;

Route::view('welcome');

// Throttle the email being sent 1 times each minute to prevent abuse.
EmailLoginRoutes::register()->middleware(['throttle:1,1']);
```

## Advanced Configuration

Mail Login was made to work out-of-the-box, but you can override the configuration by simply publishing the config file.

```shell
php artisan vendor:publish --provider="Laragear\EmailLogin\EmailLoginServiceProvider" --tag="config"
```

After that, you will receive the `config/mail-login.php` config file with an array like this:

```php
return [
    'guard' => null,

    'route' => [
        'name' => 'login.mail',
        'view' => 'mail-login::web.login',
    ],

    'minutes' => 5,

    'cache' => [
        'store' => null,
        'prefix' => 'mail-login'
    ],

    'mail' => [
        'mailer' => null,
        'connection' => null,
        'queue' => null,
        'view' => 'mail-login::mail.login',
    ],
];
```

### Guard

```php
return [
    'guard' => null,
    
    'guards' => [
        'web' => 'email'
    ]
];
```

This is the default Authentication Guard to use. When `null`, it fall backs to the application default, which is usually `web`. This is used to find user via the guard User Provider to login users. 

The `guards` configuration instructs _where_ the email lies on the Request and User. For example, using `email_address` as key will validate the `email_address` key on the Request, and find a User with that email on the `email_address` key.

> [!NOTE]
> 
> When sending an email, the guard gets imprinted in the link to avoid changes on the server.

### Route name & View

```php
return [
    'route' => [
        'name' => 'login.mail',
        'view' => 'mail-login::web.login',
    ],
];
```

This named route is linked in the email, which contains the view form to log in the user. We won't log him in directly because some mail clients will prefetch / preload the login link and may log him in by accident.

### Cache

```php
return [
    'cache' => [
        'store' => null,
        'prefix' => 'mail-login'
    ],
];
```

Email Login intents are saved into the cache for the duration of the Email Link URL. If the URL is valid, but the intent has expired or was already used, the login fails. Here you can change the store and prefix. When `null`, it will use the default application store.

### Minutes to expire

```php
return [
    'minutes' => 5,
];
```

When mailing the link, a signed URL will be generated with an expiration time. You can control how many minutes to keep the link valid until it is detected as "expired" and no longer works.

### Mail driver

```php
return [
    'mail' => [
        'mailer' => null,
        'connection' => null,
        'queue' => null,
        'view' => 'mail-login::mail.login',
    ],
];
```

This specifies which mail driver to use to send the login email, and the queue connection and name that will receive it. When `null`, it will fall back to the application default, which is usually `smtp`.

## Laravel Octane Compatibility

* There are no singletons using a stale application instance.
* There are no singletons using a stale config instance.
* There are no singletons using a stale request instance.
* The only static property accessible to write is the `LoginByMailRequest::$destroyOnRegeneration`.

There should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

### Blocking authentication after the email is sent.

Once the Login Email is sent to the user, the `LoginByEmailRequest` won't be able to block the authentication procedure since it does not check for anything more than a valid Email Login intent.

For example, if a user is banned after the login email is received, it will still be able to authenticate.

To avoid this, extend the `LoginByEmailRequest` and modify the `login()` method to add further checks on a manually retrieved user. Then use this new class on your login controller of choice.

```php
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;

class MyLoginRequest extends LoginByEmailRequest
{
    protected function login(StatefulGuard $guard) : Authenticatable|false
    {
        $user = User::query()->whereKey($this->query('id'))->whereNull('banned_at')->first();
        
        return $user && $guard->login($user)
    }
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

Laravel is a Trademark of Taylor Otwell. Copyright © 2011-2024 Laravel LLC.
