<?php

return [
    'host' => env('ELASTICSEARCH_HOST', '127.0.0.1:9200'),
    'scheme' => env('ELASTICSEARCH_SCHEME'),
    'user' => env('ELASTICSEARCH_USER'),
    'pass' => env('ELASTICSEARCH_PASS'),
];
