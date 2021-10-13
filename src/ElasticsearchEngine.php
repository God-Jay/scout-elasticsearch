<?php

namespace GodJay\ScoutElasticsearch;

use Elasticsearch\Client as ElasticsearchClient;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class ElasticsearchEngine extends Engine
{
    public $elasticsearchClient;

    protected $queryParams = [];

    /**
     * ElasticsearchEngine constructor.
     * @param ElasticsearchClient $elasticsearchClient
     */
    public function __construct(ElasticsearchClient $elasticsearchClient)
    {
        $this->elasticsearchClient = $elasticsearchClient;
    }

    /**
     * Update the given model in the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    public function update($models)
    {
        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'update' => [
                    '_id' => $model->getKey(),
                    '_index' => $model->searchableAs(),
                ]
            ];
            $params['body'][] = [
                'doc' => $model->toSearchableArray(),
                'doc_as_upsert' => true
            ];
        });

        $this->elasticsearchClient->bulk($params);
    }

    /**
     * Remove the given model from the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    public function delete($models)
    {
        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getKey(),
                    '_index' => $model->searchableAs(),
                ]
            ];
        });

        $this->elasticsearchClient->bulk($params);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param \Laravel\Scout\Builder $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'filters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param \Laravel\Scout\Builder $builder
     * @param int $perPage
     * @param int $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'filters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param \Laravel\Scout\Builder $builder
     * @param mixed $results
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results['hits']['total']['value'] === 0) {
            return $model->newCollection();
        }

        $hits = collect($results['hits']['hits'])->keyBy('_id');
        $models = $model->hydrate($hits->pluck('_source')->all())->all();
        foreach ($models as $model) {
            $model->setHighlight($hits->get($model->id)['highlight'] ?? []);
        }
        return $model->newCollection($models);
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total']['value'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function flush($model)
    {
        $model->newQuery()->unsearchable();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param \Laravel\Scout\Builder $builder
     * @param array $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $this->queryParams = $builder->generateParams($options);

        if ($builder->callback) {
            $callback = call_user_func(
                $builder->callback,
                $this,
                $builder->query,
                $this->queryParams
            );
            if ($callback instanceof ElasticsearchEngine) {
                return $callback->elasticsearchClient->search($this->queryParams);
            } else {
                return $callback;
            }
        }

        return $this->elasticsearchClient->search($this->queryParams);
    }

    /**
     * Get the filter array for the query.
     *
     * @param Builder $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            if (is_array($value)) {
                return ['terms' => [$key => $value]];
            }
            return ['match_phrase' => [$key => $value]];
        })->values()->all();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    public function createIndex($model)
    {
        $params = [
            'index' => $model->searchableAs(),
            'body' => [
                'mappings' => [
                    'properties' => $model->getElasticMapping()
                ]
            ]
        ];
        return $this->elasticsearchClient->indices()->create($params);
    }

    /**
     * @param array $queryParams
     * @return ElasticsearchEngine
     */
    public function setQueryParams(array $queryParams): ElasticsearchEngine
    {
        $this->queryParams = $queryParams;
        return $this;
    }

    /**
     * @param Builder $builder
     * @return array
     */
    public function debugSearch(Builder $builder): array
    {
        try {
            $result = $this->get($builder);
            $exception = null;
        } catch (\Exception $e) {
            $result = null;
            $exception = $e->getMessage();
        }
        return ['result' => $result, 'query_params' => $this->queryParams, 'exception' => $exception];
    }
}
