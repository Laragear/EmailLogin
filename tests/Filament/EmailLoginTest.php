<?php

namespace Tests\Filament;

use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Support\SupportServiceProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginBuilder;
use Laragear\EmailLogin\EmailLoginIntent as LoginIntent;
use Laragear\EmailLogin\Filament\EmailLogin;
use Laragear\EmailLogin\Http\Requests\LoginByEmailRequest;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Mockery;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Tests\Fixtures\TestPanelProvider;
use Tests\TestCase;
use function __;
use function version_compare;

class EmailLoginTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            // required to render @capture
            BladeCaptureDirectiveServiceProvider::class,
            // Required to make filament work.
            SupportServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            ActionsServiceProvider::class,
            FilamentServiceProvider::class,
            TestPanelProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        $this->markTestSkippedWhen(
            version_compare(Application::VERSION, '13.0.0', '<'), 'Filament 5.x does not support Laravel 13.x'
        );

        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_shows_login_when_not_login_intent_token_nor_store(): void
    {
        Livewire::test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', false)
            ->assertSee('Email address')
            ->assertSee('Remember')
            ->assertSee('Login with Email')
            ->assertDontSee('Authenticate');
    }

    public function test_shows_login_when_not_login_intent_store(): void
    {
        Livewire::withQueryParams([
            LoginByEmailRequest::TOKEN_KEY => 'test-token',
        ])->test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', false)
            ->assertSee('Email address')
            ->assertSee('Remember')
            ->assertSee('Login with Email')
            ->assertDontSee('Authenticate');
    }

    public function test_shows_login_when_not_login_intent_token(): void
    {
        Livewire::withQueryParams([
            LoginByEmailRequest::STORE_KEY => 'test-store',
        ])->test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', false)
            ->assertSee('Email address')
            ->assertSee('Remember')
            ->assertSee('Login with Email')
            ->assertDontSee('Authenticate');
    }

    public function test_shows_login_when_login_intent_invalid(): void
    {
        $this->mock(EmailLoginBroker::class, function (Mockery\MockInterface $mock) {
            $mock->expects('store')->with('test-store')->andReturnSelf();
            $mock->expects('get')->with('test-token')->andReturnNull();
        });

        Livewire::withQueryParams([
            LoginByEmailRequest::TOKEN_KEY => 'test-token',
            LoginByEmailRequest::STORE_KEY => 'test-store',
        ])->test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', false)
            ->assertSee('Email address')
            ->assertSee('Remember')
            ->assertSee('Login with Email')
            ->assertDontSee('Authenticate');
    }

    public function test_shows_login_when_login_intent_invalid_store(): void
    {
        $this->mock(EmailLoginBroker::class, function (Mockery\MockInterface $mock) {
            $mock->expects('store')->with('test-store')->andThrow(new InvalidArgumentException());
        });

        Livewire::withQueryParams([
            LoginByEmailRequest::TOKEN_KEY => 'test-token',
            LoginByEmailRequest::STORE_KEY => 'test-store',
        ])->test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', false)
            ->assertSee('Email address')
            ->assertSee('Remember')
            ->assertSee('Login with Email')
            ->assertDontSee('Authenticate');
    }

    public function test_shows_sign_in_when_login_intent_valid(): void
    {
        $this->mock(EmailLoginBroker::class, function (Mockery\MockInterface $mock) {
            $mock->expects('store')->with('test-store')->andReturnSelf();
            $mock->expects('get')->with('test-token')->andReturn(new LoginIntent('', '', true, null, []));
        });

        Livewire::withQueryParams([
            LoginByEmailRequest::TOKEN_KEY => 'test-token',
            LoginByEmailRequest::STORE_KEY => 'test-store',
        ])->test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', true)
            ->assertDontSee('Email address')
            ->assertDontSee('Remember')
            ->assertDontSee('Login with Email')
            ->assertSee('Sign in');
    }

    public function test_form_sends_email(): void
    {
        $this->mock(EmailLoginBuilder::class, function (Mockery\MockInterface $mock) {
            $mock->expects('withRemember')->with(Mockery::type('bool'))->andReturnSelf();
            $mock->expects('withCredentials')->with(['email' => 'test-email@email.com'])->andReturnSelf();
            $mock->expects('withAction')->with(EmailLogin::class)->andReturnSelf();
            $mock->expects('withStore')->with(null)->andReturnSelf();
            $mock->expects('withGuard')->with('web')->andReturnSelf();
            $mock->expects('withThrottle')->with(30)->andReturnSelf();
            $mock->expects('send');
        });

        Livewire::test(EmailLogin::class)
            ->set('data.email', 'test-email@email.com')
            ->call('sendLoginEmail');

        Notification::assertNotified(
            Notification::make('laragear::email-login.sent')
            ->title(__('laragear::email-login.sent.title', ['email' => 'test-email@email.com']))
            ->body(__('laragear::email-login.sent.body'))
            ->icon(Heroicon::Envelope)
            ->color(Color::Green),
        );
    }

    public function test_authenticates_fails(): void
    {
        $this->mock(EmailLoginBroker::class, function (Mockery\MockInterface $mock) {
            $mock->expects('store')->with('test-store')->twice()->andReturnSelf();
            $mock->expects('get')->with('test-token')->andReturn(new LoginIntent('', '', true, null, []));
            $mock->expects('pull')->with('test-token')->andReturnNull();
        });

        $event = Event::fake();

        Livewire::withQueryParams([
            LoginByEmailRequest::TOKEN_KEY => 'test-token',
            LoginByEmailRequest::STORE_KEY => 'test-store',
        ])->test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', true)
            ->call('authenticate')
            ->assertSet('isLoggingInFromEmail', false)
            ->assertSee('Email address')
            ->assertSee('Remember')
            ->assertSee('Login with Email')
            ->assertDontSee('Authenticate');

        $event->assertDispatched(Failed::class);

        Notification::assertNotified(
            Notification::make('laragear::email-login.failed')
                ->title(__('laragear::email-login.failed.title'))
                ->body(__('laragear::email-login.failed.body'))
                ->icon(Heroicon::XCircle)
                ->color(Color::Amber),
        );
    }

    public function test_authenticate_success(): void
    {
        $this->mock(EmailLoginBroker::class, function (Mockery\MockInterface $mock) {
            $intent = new LoginIntent('', '', true, null, []);

            $mock->expects('store')->with('test-store')->twice()->andReturnSelf();
            $mock->expects('get')->with('test-token')->andReturn($intent);
            $mock->expects('pull')->with('test-token')->andReturn($intent);
        });

        $guard = $this->mock(StatefulGuard::class, function (Mockery\MockInterface $mock) {
            $user = new User();
            $mock->expects('check')->andReturnFalse();
            $mock->expects('getProvider->retrieveById')->andReturn($user);
            $mock->expects('login')->with($user, true);
        });

        $this->mock(AuthFactory::class, function (Mockery\MockInterface $mock) use ($guard) {
            $mock->expects('guard')->with('web')->twice()->andReturn($guard);
        });

        Livewire::withQueryParams([
            LoginByEmailRequest::TOKEN_KEY => 'test-token',
            LoginByEmailRequest::STORE_KEY => 'test-store',
        ])->test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', true)
            ->call('authenticate')
            ->assertHasNoErrors();
    }

    public function test_authenticate_fails_if_panel_access_is_denied(): void
    {
        $this->mock(EmailLoginBroker::class, function (Mockery\MockInterface $mock) {
            $intent = new LoginIntent('', '', true, null, []);

            $mock->expects('store')->with('test-store')->twice()->andReturnSelf();
            $mock->expects('get')->with('test-token')->andReturn($intent);
            $mock->expects('pull')->with('test-token')->andReturn($intent);
        });

        $guard = $this->mock(StatefulGuard::class, function (Mockery\MockInterface $mock) {
            $user = new class extends User implements FilamentUser
            {
                public function canAccessPanel(Panel $panel): bool
                {
                    return false;
                }
            };

            $mock->expects('check')->andReturnFalse();
            $mock->expects('getProvider->retrieveById')->andReturn($user);
            $mock->expects('login')->never();
        });

        $this->mock(AuthFactory::class, function (Mockery\MockInterface $mock) use ($guard) {
            $mock->expects('guard')->with('web')->twice()->andReturn($guard);
        });

        $event = Event::fake();

        Livewire::withQueryParams([
            LoginByEmailRequest::TOKEN_KEY => 'test-token',
            LoginByEmailRequest::STORE_KEY => 'test-store',
        ])->test(EmailLogin::class)
            ->assertSet('isLoggingInFromEmail', true)
            ->call('authenticate')
            ->assertHasErrors();

        $event->assertDispatched(Failed::class);
    }
}
