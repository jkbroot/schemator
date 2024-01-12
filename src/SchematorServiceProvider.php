<?php


use Console\Commands\SchematorCommand;
use Illuminate\Support\ServiceProvider;

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
