<?php

namespace Laravel\Nova\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    use ResolvesStubPath;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nova:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the Nova resources';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->comment('Publishing Nova Assets / Resources...');
        $this->callSilent('nova:publish');

        $this->comment('Publishing Nova Service Provider...');
        $this->callSilent('vendor:publish', ['--tag' => 'nova-provider']);

        $this->installNovaServiceProvider();

        $this->comment('Generating User Resource...');
        $this->callSilent('nova:resource', ['name' => 'User']);
        copy($this->resolveStubPath('/stubs/nova/user-resource.stub'), app_path('Nova/User.php'));

        if (file_exists(app_path('Models/User.php'))) {
            file_put_contents(
                app_path('Nova/User.php'),
                str_replace('App\User::class', 'App\Models\User::class', file_get_contents(app_path('Nova/User.php')))
            );
        }

        $this->setAppNamespace();

        $this->info('Nova scaffolding installed successfully.');
    }

    /**
     * Install the Nova service providers in the application configuration file.
     *
     * @return void
     */
    protected function installNovaServiceProvider()
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        if (! Str::contains($appConfig = file_get_contents(config_path('app.php')), "{$namespace}\\Providers\\NovaServiceProvider::class")) {
            file_put_contents(config_path('app.php'), str_replace(
                "{$namespace}\\Providers\EventServiceProvider::class,".PHP_EOL,
                "{$namespace}\\Providers\EventServiceProvider::class,".PHP_EOL."        {$namespace}\Providers\NovaServiceProvider::class,".PHP_EOL,
                $appConfig
            ));
        }
    }

    /**
     * Set the proper application namespace on the installed files.
     *
     * @return void
     */
    protected function setAppNamespace()
    {
        $namespace = $this->laravel->getNamespace();

        $this->setAppNamespaceOn(app_path('Nova/User.php'), $namespace);
        $this->setAppNamespaceOn(app_path('Providers/NovaServiceProvider.php'), $namespace);
    }

    /**
     * Set the namespace on the given file.
     *
     * @param string $file
     * @param string $namespace
     *
     * @return void
     */
    protected function setAppNamespaceOn($file, $namespace)
    {
        file_put_contents($file, str_replace(
            'App\\',
            $namespace,
            file_get_contents($file)
        ));
    }
}
