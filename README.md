# god-jay scout-elasticsearch

Use elasticsearch as easy as using Eloquent ORM in your laravel application.

English | [简体中文](README.zh-cn.md)

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
    * [Create elasticsearch index](#create-elasticsearch-index)
    * [Import the given model into the search index](#import-the-given-model-into-the-search-index)
    * [Flush all of the model's records from the index](#flush-all-of-the-model's-records-from-the-index)
    * [Adding Records](#adding-records)
    * [Updating Records](#updating-records)
    * [Removing Records](#removing-records)
- [Searching](#searching)
- [Advanced Usage](#advanced-usage)

## Installation

You can install the package via composer:

``` bash
composer require god-jay/scout-elasticsearch
```

After installing the package, you should publish the Scout configuration using the vendor:publish Artisan command. This
command will publish the scout.php configuration file to your config directory:

``` bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

Then add

```
SCOUT_DRIVER=elastic
ELASTICSEARCH_HOST=your_es_host_ip:port

#Add if should auth es user
ELASTICSEARCH_USER=your_es_user
ELASTICSEARCH_PASS=your_es_pass
```

### Docker compose run es + kibana

If you don't have your own es service, you may install and run es + kibana with docker compose:

- You should install docker compose first: [install docker compose](https://docs.docker.com/compose/install/)

- Then run the command in the root of this directory:

  ``` bash
  docker-compose up -d
  ```

- You can browse `http://localhost:5601` to visit kibana.

- To stop docker containers, run the command in the root of this directory:

  ``` bash
  docker-compose down
  ```

in your .env file.

## Configuration

Assuming there is a `posts` table and a Post Model, the simplified table may looks like:

| id | title | content | created_at |
| :---: | :---: | :---: | :---: |
| 1 | 标题 | 文本内容 | 2020-01-01 01:01:01 |

Use GodJay\ScoutElasticsearch\Searchable in your model:

```php
namespace App\Models;

use GodJay\ScoutElasticsearch\Searchable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Searchable;
}
```

Add searchableAs function in the model:

```php
public function searchableAs()
{
    //elasticsearch index name, you can set any name you like in the model
    return 'posts';
}
```

## Usage

### Create elasticsearch index

Add getElasticMapping function in the model,

then run `php artisan elastic:create-index "App\Models\Post"`

For more details,
see [Create index API](https://www.elastic.co/guide/en/elasticsearch/reference/master/indices-create-index.html)

```php
public function getElasticMapping()
{
    return [
        'title' => [
            'type' => 'text',
            'analyzer' => 'ik_max_word',
            'search_analyzer' => 'ik_smart',
        ],
        'content' => [
            'type' => 'text',
            'analyzer' => 'ik_max_word',
            'search_analyzer' => 'ik_smart',
        ],
    ];
}
```

The elasticsearch index will be like:

```json
{
  "mapping": {
    "_doc": {
      "properties": {
        "content": {
          "type": "text",
          "analyzer": "ik_max_word",
          "search_analyzer": "ik_smart"
        },
        "title": {
          "type": "text",
          "analyzer": "ik_max_word",
          "search_analyzer": "ik_smart"
        }
      }
    }
  }
}
```

### Import the given model into the search index

If there already exist many rows in your table, and you want to import the rows to elasticsearch,

Add toSearchableArray function in the model, then run `php artisan scout:import "App\Models\Post"`

```php
public function toSearchableArray()
{
    return [
       'id' => $this->attributes['id'],
       'title' => $this->attributes['title'],
       'content' => strip_tags($this->attributes['content']),
       'created_at' => $this->attributes['created_at'],
   ];
}
```

After import the rows from table above, the elasticsearch index will be like:

```json
{
  "mapping": {
    "_doc": {
      "properties": {
        "content": {
          "type": "text",
          "analyzer": "ik_max_word",
          "search_analyzer": "ik_smart"
        },
        "created_at": {
          "type": "text",
          "fields": {
            "keyword": {
              "type": "keyword",
              "ignore_above": 256
            }
          }
        },
        "id": {
          "type": "long"
        },
        "title": {
          "type": "text",
          "analyzer": "ik_max_word",
          "search_analyzer": "ik_smart"
        }
      }
    }
  }
}
```

### Flush all of the model's records from the index

Run `php artisan scout:flush "App\Models\Post"`

### Adding Records

Once you have added the Searchable trait to a model, all you need to do is save a model instance and it will
automatically be added to your search index.

```php
$post = new Post();

// ...

$post->save();
``` 

### Updating Records

To update a searchable model, you only need to update the model instance's properties and save the model to your
database.

```php
$post = Post::find(1);

// Update the order...

$post->save();
``` 

### Removing Records

To remove a record from your index, delete the model from the database. This form of removal is even compatible with
soft deleted models:

```php
$post = Post::find(1);

$post->delete();
``` 

## Searching

Base:

```php
$posts = Post::search('内容')->get();
```

Paginate:

```php
$posts = Post::search('内容')->paginate(10);
```

Highlight:

```php
$post = Post::search('内容')->highlight(['title' => null, 'content' => null])->first();
```

The search result will be:

```php
App\Models\Post Object
(
    [table:protected] => ppp
    ...
    [attributes:protected] => [
        [id] => 1
        [title] => 标题
        [content] => 文本内容
        [created_at] => 2020-01-01 01:01:01
    ]
    [relations:protected] => [
        [highlight] => GodJay\ScoutElasticsearch\Highlight Object
        (
            [attributes:protected] => [
                [content] => [
                    [0] => 文本<em>内容</em>
                ]
            ]
        )
    ]
)
```

## Advanced Usage

ES script sort:

```php
use GodJay\ScoutElasticsearch\ElasticsearchEngine;

$posts = Post::search('', function (ElasticsearchEngine $engine, string $query, array $params) {
    $params['body']['sort'] = array_merge([[
        '_script' => [
            'type' => 'number',
            'script' => ['source' => "doc['field_a'].value * 0.7 + doc['field_b'].value * 0.3"],
            'order' => 'desc'
        ]
    ]], $params['body']['sort'] ?? []);
    $engine->setQueryParams($params);
    return $engine;
})->orderBy('id', 'desc')->where('field_c', 1)->get();
```

Debug:

```php
use GodJay\ScoutElasticsearch\ElasticsearchEngine;

$debug = Post::search('', function (ElasticsearchEngine $engine, string $query, array $params) {
    $params['body']['sort'] = array_merge([[
        '_script' => [
            'type' => 'number',
            'script' => ['source' => "doc['field_a'].value * 0.7 + doc['field_b'].value * 0.3"],
            'order' => 'desc'
        ]
    ]], $params['body']['sort'] ?? []);
    $engine->setQueryParams($params);
    return $engine;
})->orderBy('id', 'desc')->where('field_c', 1)->where('field_d', ['x', 'y'])->debugSearch();
```

The result will be:

```php
Array
(
    [result] => Illuminate\Database\Eloquent\Collection Object
    ...
    [query_params] => Array
    ...
    [exception] => 
    ...
)
```

The json string of `$debug['query_params']` will be:

```json
{
  "index": "posts",
  "body": {
    "sort": [
      {
        "_script": {
          "type": "number",
          "script": {
            "source": "doc['field_a'].value * 0.7 + doc['field_b'].value * 0.3"
          },
          "order": "desc"
        }
      },
      {
        "id": "desc"
      }
    ],
    "query": {
      "bool": {
        "must": [
          {
            "match_phrase": {
              "field_c": 1
            }
          },
          {
            "terms": {
              "field_d": [
                "x",
                "y"
              ]
            }
          }
        ]
      }
    }
  }
}
```