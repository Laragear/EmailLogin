<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Config\Repository as ConfigContract;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use function __;
use function session;

class EmailLoginController extends Controller
{
    /**
     * Send the email for the request.
     */
    public function send(EmailLoginRequest $request): RedirectResponse
    {
        return tap($request->send(), fn () => session()->flash(
            'login', __('The login email has been sent to').' '.$request->input('email')
        ));
    }

    /**
     * Returns the view for the login attempt.
     */
    public function show(ConfigContract $config, ViewFactoryContract $view): View
    {
        return $view->make($config->get('email-login.route.view'));
    }

    /**
     * Validates and redirects the user
     *
     * @param  \Laragear\EmailLogin\Http\Requests\LoginByEmailRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(LoginByEmailRequest $request): RedirectResponse
    {
        return $request->redirect()->intended();
    }
}
