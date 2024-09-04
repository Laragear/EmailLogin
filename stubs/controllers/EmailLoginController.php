<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Config\Repository as ConfigContract;
use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laragear\EmailLogin\Http\Requests\EmailLoginRequest;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Laragear\TokenAction\Store;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function back;

class EmailLoginController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ConfigContract $config)
    {
        $guard = $this->config->get('email-login.defaults.guard');

        $this->middleware('guest'. ($guard ? ":$guard" : ''));
        $this->middleware('token.validate')->only('show');
        $this->middleware('token.consume')->only('login');
    }

    /**
     * Send the email for the request.
     */
    public function send(EmailLoginRequest $request, TranslatorContract $translator): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $request->thottle(
            $this->config->get('email-login.throttle.seconds'),
            $this->config->get('email-login.throttle.store'),
        )->send();

        $request->session()->flash(
            'login', $translator->get('The login email has been sent to :email.', ['email' => $request->email])
        );

        return back();
    }

    /**
     * Returns the view for the login attempt.
     */
    public function show(Request $request, Store $store, ConfigContract $config, ViewFactoryContract $view): View
    {
        if ($store->get($request->query('token'))) {
            return $view->make($config->get('email-login.route.view'));
        }

        throw new NotFoundHttpException();
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
