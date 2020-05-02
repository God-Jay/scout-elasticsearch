<?php

namespace GodJay\ScoutElasticsearch;

use Eloquent;

trait Searchable
{
    use \Laravel\Scout\Searchable;

    /**
     * Perform a search against the model's indexed data.
     *
     * @param string $query
     * @param \Closure $callback
     * @return \GodJay\ScoutElasticsearch\ElasticsearchBuilder
     */
    public static function search($query = '', $callback = null)
    {
        return app(ElasticsearchBuilder::class, [
            'model' => new static,
            'query' => $query,
            'callback' => $callback,
            'softDelete' => static::usesSoftDelete() && config('scout.soft_delete', false),
        ]);
    }

    public function searchableUsing()
    {
        return app(ElasticsearchEngineManager::class)->engine();
    }

    public function setHighlight($highlight)
    {
        $this->setRelation('highlight', new Highlight($highlight));
    }

    public function getElasticMapping()
    {
        return [];
    }

    public static function createIndex()
    {
        $self = new static;
        $self->searchableUsing()->createIndex($self);
    }
}
