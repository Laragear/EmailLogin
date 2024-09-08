<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use function back;
use function config;
use function __;

class EmailLoginController extends Controller
{
    /**
     * Send the email for the request.
     */
    public function send(EmailLoginRequest $request): RedirectResponse
    {
        $request->throttleBy(30, config('email-login.throttle.store'), config('email-login.throttle.prefix'))->send();

        $request->session()->flash(
            'sent', __('The login email has been sent to :email.', ['email' => $request->email])
        );

        return back();
    }

    /**
     * Returns the view for the login attempt.
     */
    public function show(LoginByEmailRequest $request, ViewFactoryContract $view): View
    {
        return $view->make(config('email-login.route.view'), [
            'token' => $request->input('token'),
            'store' => $request->input('store')
        ]);
    }

    /**
     * Validates and redirects the user
     *
     * @param  \Laragear\EmailLogin\Http\Requests\LoginByEmailRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(LoginByEmailRequest $request): RedirectResponse
    {
        return $request->toIntended();
    }
}
