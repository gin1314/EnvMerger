<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Laravel;

use Gin\EnvMerger\Commands\MergeCommand;

if (class_exists(\Illuminate\Support\ServiceProvider::class)) {
    /**
     * @property mixed $app
     */
    class EnvMergerServiceProvider extends \Illuminate\Support\ServiceProvider
    {
        public function boot(): void
        {
            if (isset($this->app) && method_exists($this->app, 'runningInConsole') && $this->app->runningInConsole()) {
                $this->commands([
                    MergeCommand::class,
                ]);
            }
        }
    }
} else {
    class EnvMergerServiceProvider
    {
        /** @var mixed */
        protected mixed $app = null;

        public function boot(): void
        {
            // No-op in environments without illuminate/support
        }
    }
}
