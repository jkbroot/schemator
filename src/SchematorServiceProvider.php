<?php

namespace Jkbcoder\Schemator;

use Illuminate\Support\ServiceProvider;
use Jkbcoder\Schemator\Console\Commands\SchematorCommand;

class SchematorServiceProvider extends ServiceProvider {
    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SchematorCommand::class,
            ]);
        }
    }

    public function register() {
        // Register services, bindings, etc.
    }
}
