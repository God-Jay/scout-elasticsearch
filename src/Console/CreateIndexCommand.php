<?php

namespace GodJay\ScoutElasticsearch\Console;

use Illuminate\Console\Command;
use Elasticsearch\ClientBuilder;

class CreateIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:create-index {model}';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $class = $this->argument('model');

        $model = new $class;

        $model::createIndex();
    }
}
