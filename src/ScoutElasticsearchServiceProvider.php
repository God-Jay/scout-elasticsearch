<?php

namespace GodJay\ScoutElasticsearch;

use GodJay\ScoutElasticsearch\Console\CreateIndexCommand;
use GodJay\ScoutElasticsearch\Console\UpdateMappingCommand;
use Illuminate\Support\ServiceProvider;

class ScoutElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/elasticsearch.php', 'elasticsearch');

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateMappingCommand::class,
                CreateIndexCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/elasticsearch.php' => $this->app['path.config'] . DIRECTORY_SEPARATOR . 'elasticsearch.php',
            ]);
        }
    }
}
