<?php

namespace Laragear\EmailLogin\Filament;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Laragear\EmailLogin\EmailLoginBroker;
use Laragear\EmailLogin\EmailLoginBuilder;
use Livewire\Attributes\Url;
use SensitiveParameter;
use function __;
use function app;
use function filament;
use function request;

class EmailLogin extends Login
{
    /**
     * If the session should be destroyed on regeneration
     */
    public static bool $destroyOnRegeneration = true;

    /**
     * If login was hit from the Login Email rather than normal navigation.
     *
     * @var bool
     */
    public bool $isLoggingInFromEmail = false;

    /**
     * The token to find the intent for.
     */
    #[Url]
    public ?string $token = null;

    /**
     * The store to use to find the token.
     */
    #[Url]
    public ?string $store = null;

    public function mount(): void
    {
        $this->isLoggingInFromEmail = $this->hasValidToken();

        parent::mount();
    }

    /**
     * Check if the login token is valid.
     */
    protected function hasValidToken(): bool
    {
        if ($this->token && $this->store && request()->isMethod('get')) {
            try {
                return (bool) app(EmailLoginBroker::class)->store($this->store)->get($this->token);
            } catch (InvalidArgumentException) {
                return false;
            }
        }

        return false;
    }

    /**
     * Returns the store to use for saving and retrieving the Login Token.
     *
     * @return string|void
     */
    protected function useTokenStore()
    {
        //
    }

    public function form(Schema $schema): Schema
    {
        if (!$this->isLoggingInFromEmail) {
            $schema->components([
                $this->getEmailFormComponent(),
                $this->getRememberFormComponent(),
            ]);
        }

        return $schema;
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
            $this->getSendEmailComponent(),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->visible($this->isLoggingInFromEmail);
    }

    /**
     * Show a button to log in to the application.
     */
    protected function getSendEmailComponent(): Action
    {
        return Action::make('send-email')
            ->label(__('laragear::email-login.send.label'))
            ->hidden($this->isLoggingInFromEmail)
            ->action($this->sendLoginEmail(...));
    }

    /**
     * Send the Email Login to the user.
     */
    public function sendLoginEmail(): void
    {
        $this->buildLoginEmailBuilder()->send();

        $this->notifyEmailSent()?->send();
    }

    /**
     * Modify the Email Login Builder.
     */
    protected function buildLoginEmailBuilder(): EmailLoginBuilder
    {
        $state = $this->form->getState();

        return app(EmailLoginBuilder::class)
            ->withRemember($state['remember'] ?? false)
            ->withCredentials($this->getCredentialsFromFormData($state))
            ->withAction(static::class)
            ->withStore($this->useTokenStore())
            ->withGuard(filament()->getAuthGuard())
            ->withThrottle(30);
    }

    /**
     * Sends a notification for the email sent.
     */
    protected function notifyEmailSent(): ?Notification
    {
        return Notification::make('laragear::email-login.sent')
            ->title(__('laragear::email-login.sent.title', ['email' => $this->form->getStateSnapshot()['email'] ?? null]))
            ->body(__('laragear::email-login.sent.body'))
            ->icon(Heroicon::Envelope)
            ->color(Color::Green);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'email' => $data['email'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $authGuard = Filament::auth();

        $broker = app(EmailLoginBroker::class);

        try {
            $intent = $broker->store($this->store)->pull($this->token) ?? throw new InvalidArgumentException();
        } catch (InvalidArgumentException) {
            $this->fireFailedEvent($authGuard, null, []);

            $this->handleInvalidToken();

            return null;
        }

        $user = $authGuard->getProvider()->retrieveById($intent->id); /** @phpstan-ignore-line */

        if ($user instanceof FilamentUser && !$user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            // Ensure the event receives an Authenticatable instance or null to match the signature.
            $this->fireFailedEvent($authGuard, $user instanceof Authenticatable ? $user : null, []);
            $this->throwFailureValidationException();
        }

        $authGuard->login($user, $intent->remember);

        session()->regenerate(static::$destroyOnRegeneration);

        return app(LoginResponse::class);
    }

    /**
     * Handles the invalid token.
     *
     * @return void|never
     */
    protected function handleInvalidToken()
    {
        $this->notifyInvalidToken()?->send();

        $this->isLoggingInFromEmail = false;
    }

    /**
     * Notify the user the token is invalid.
     */
    protected function notifyInvalidToken(): ?Notification
    {
        return Notification::make('laragear::email-login.failed')
            ->title(__('laragear::email-login.failed.title'))
            ->body(__('laragear::email-login.failed.body'))
            ->icon(Heroicon::XCircle)
            ->color(Color::Amber);
    }
}
