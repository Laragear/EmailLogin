<?php

namespace Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Laragear\EmailLogin\Filament\EmailLogin;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('test')
            ->login(EmailLogin::class)
            ->brandLogo(app()->runningUnitTests() ? null : 'logo')
            ->brandName('Test App')
            ->default();
    }
}
