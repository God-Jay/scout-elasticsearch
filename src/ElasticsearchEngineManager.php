<?php

namespace GodJay\ScoutElasticsearch;

use Elasticsearch\ClientBuilder;
use Laravel\Scout\EngineManager;

class ElasticsearchEngineManager extends EngineManager
{
    public function createElasticDriver()
    {
        return new ElasticsearchEngine(ClientBuilder::create()->setHosts([config('elasticsearch')])->build());
    }
}
