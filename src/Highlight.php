<?php

namespace GodJay\ScoutElasticsearch;

class Highlight
{
    protected $attributes = [];

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }
}
