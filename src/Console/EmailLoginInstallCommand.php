<?php

namespace Laragear\EmailLogin\Console;

use Illuminate\Console\Command;
use Laragear\EmailLogin\EmailLoginServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand('email-login:install', 'Publish the assets from the Laragear Email Login package.')]
class EmailLoginInstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'email-login:install
                            {--existing : Publish and overwrite only the files that have already been published}
                            {--force : Overwrite the existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the assets from the Laragear Email Login package.';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->call('vendor:publish', [
            '--provider' => EmailLoginServiceProvider::class,
            '--existing' => $this->option('existing'),
            '--force' => $this->option('force'),
            '--tag' => ['views', 'controllers', 'config']
        ]);
    }
}
