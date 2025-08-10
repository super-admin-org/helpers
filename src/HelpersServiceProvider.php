<?php

namespace SuperAdmin\Admin\Helpers;

use Illuminate\Support\ServiceProvider;
use SuperAdmin\Admin\Helpers\Console\Commands\GenerateMySQLHelperScaffold;
use SuperAdmin\Admin\Helpers\Console\Commands\GeneratePgHelperScaffold;
use SuperAdmin\Admin\Helpers\Console\Commands\GenerateSeedersFromTables;
use SuperAdmin\Admin\Helpers\Console\Commands\RunHelperScaffoldGeneration;

class HelpersServiceProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected $commands = [
        GenerateMySQLHelperScaffold::class,
        GeneratePgHelperScaffold::class,
        GenerateSeedersFromTables::class,
        RunHelperScaffoldGeneration::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'super-admin-helpers');

        Helpers::boot();
    }

    public function register()
    {
        $this->commands($this->commands);
    }
}
