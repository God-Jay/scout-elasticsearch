# god-jay scout-elasticsearch

在laravel项目中像使用Eloquent ORM一样简单地使用elasticsearch

[English](README.md) | 简体中文

## 目录

- [安装](#安装)
- [配置](#配置)
- [使用](#使用)
    * [创建 elasticsearch index](#创建-elasticsearch-index)
    * [将模型表中的数据导入到elasticsearch](#将模型表中的数据导入到-elasticsearch)
    * [从索引中删除所有数据](#从索引中删除所有数据)
    * [增加记录](#增加记录)
    * [更新记录](#更新记录)
    * [移除记录](#移除记录)
- [搜索](#搜索)
- [高级用法](#高级用法)

## 安装

你可以通过composer安装此包：

``` bash
composer require god-jay/scout-elasticsearch
```

安装完成后，需要使用vendor:publish命令，来生成Scout配置文件。这个命令将会在你的config目录下生成一个 scout.php

``` bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

然后在你的.env文件中添加两行

```
SCOUT_DRIVER=elastic
ELASTICSEARCH_HOST=your_es_host_ip:port
```

## 配置

假设我们有一个`posts`表以及一个Post模型，简化的表可能有以下结构数据：

| id | title | content | created_at |
| :---: | :---: | :---: | :---: |
| 1 | 标题 | 文本内容 | 2020-01-01 01:01:01 |

在你的模型中使用GodJay\ScoutElasticsearch\Searchable：

```php
namespace App\Models;

use GodJay\ScoutElasticsearch\Searchable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Searchable;
}
```

在该模型中增加searchableAs方法：

```php
public function searchableAs()
{
    //elasticsearch index的名称，可以随意取
    return 'posts';
}
```

## 使用

### 创建 Elasticsearch Index

在该模型中增加getElasticMapping方法，

然后运行`php artisan elastic:create-index "App\Models\Post"`命令

更多详情请查看elastic
search官方文档：[Create index API](https://www.elastic.co/guide/en/elasticsearch/reference/master/indices-create-index.html)

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

创建出的elasticsearch索引：

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

### 将模型表中的数据导入到 Elasticsearch

如果在该表中，已经存在许多数据，你想将这些数据导入到elasticsearch，

在模型中增加toSearchableArray方法，然后运行`php artisan scout:import "App\Models\Post"`

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

将这些数据导入到elasticsearch中后，elasticsearch index将会变成这样：

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

### 从索引中删除所有数据

运行`php artisan scout:flush "App\Models\Post"`

### 增加记录

只要在模型中使用Searchable，只需要执行保存，就可以自动将该记录同步到elasticsearch

```php
$post = new Post();

// ...

$post->save();
``` 

### 更新记录

要更新一个搜索记录，只要更新模型的属性值，然后保存即可

```php
$post = Post::find(1);

// Update the order...

$post->save();
``` 

### 移除记录

要从elasticsearch中移除一个记录，只要执行删除操作

```php
$post = Post::find(1);

$post->delete();
``` 

## 搜索

基础使用：

```php
$posts = Post::search('内容')->get();
```

分页：

```php
$posts = Post::search('内容')->paginate(10);
```

高亮：

```php
$post = Post::search('内容')->highlight(['title' => null, 'content' => null])->first();
```

以上数据的搜索结果：

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

## 高级用法

ES script 排序：

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

Debug：

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

结果为：

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

其中，`$debug['query_params']`转成json为：

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