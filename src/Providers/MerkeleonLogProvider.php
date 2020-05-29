<?php

namespace Merkeleon\Log\Providers;

use Illuminate\Support\ServiceProvider;
use Merkeleon\Log\Console\Commands\BulkInsertToStorage;

class MerkeleonLogProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            dirname(__DIR__) . '/config/merkeleon_log.php' => config_path('merkeleon_log.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                BulkInsertToStorage::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/config/merkeleon_log.php', 'merkeleon_log'
        );
    }
}