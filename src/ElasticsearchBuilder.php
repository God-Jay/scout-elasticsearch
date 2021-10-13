<?php

namespace GodJay\ScoutElasticsearch;

use Laravel\Scout\Builder;

class ElasticsearchBuilder extends Builder
{
    protected $highlight = [];

    /**
     * @param $field
     * @param $tag
     * @return $this
     */
    public function highlight($field, $tag = null)
    {
        if (is_array($field)) {
            return $this->addArrayOfHighlight($field);
        }
        $this->highlight[$field] = $tag ?? new \stdClass();
        return $this;
    }

    /**
     * @param array $fields
     * @return $this
     */
    protected function addArrayOfHighlight($fields)
    {
        foreach ($fields as $field => $tag) {
            $this->highlight($field, $tag);
        }
        return $this;
    }

    /**
     * @param $options
     * @return array
     */
    public function generateParams($options)
    {
        $params = [
            'index' => $this->index ?? $this->model->searchableAs()
        ];
        if ($query = $this->query) {
            $params['body'] = [
                'query' => [
                    'bool' => [
//                        'must' => ['query_string' => ['query' => "*{$this->query}*"]]
                        'must' => [['query_string' => ['query' => $query]]]
                    ]
                ]
            ];
        }

        if ($sort = $this->sort()) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if ($this->highlight) {
            $params['body']['highlight']['fields'] = $this->highlight;
        }

        if (isset($options['filters']) && count($options['filters'])) {
            $params['body']['query']['bool']['must'] =
                array_merge($params['body']['query']['bool']['must'] ?? [], $options['filters']);
        }

        return $params;
    }

    /**
     * Generates the sort if theres any.
     *
     * @return array|null
     */
    protected function sort()
    {
        if (count($this->orders) == 0) {
            return null;
        }

        return collect($this->orders)->map(function ($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }

    public function debugSearch()
    {
        return $this->engine()->debugSearch($this);
    }
}
