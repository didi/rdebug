## ElasticSearch

### 创建index
流量写入ES前,需要为`SessionId`字段设置下数据类型,由于ES默认会将该字段设置为`text`,导致在`term`查找的时候,因为`-`导致分词

```
PUT /rdebug_index
{
    "mappings": {
        "_doc": {
            "properties": {
                "NextSessionId": {
                    "type": "keyword"
                },
                "SessionId": {
                    "type": "keyword"
                }
            }
        }
    }
}
```

### 使用别名
建议通过别名使用ES
```
POST _aliases
{
    "actions": [
        {
            "add": {
                "index": "rdebug_index",
                "alias": "alias_rdebug_index"
            }
        }
    ]
}
```

### 配置midi
在执行 `midi` 命令的目录下，新建或修改 `.midi/Config.yml`

增加扩展命令和ES地址
```
php:
    
    # for elastic
    preload-plugins:
        - Midi\ElasticPlugin
    session-resolver: Midi\Resolver\ElasticResolver
    
    # set your elastic search url
    # http://username:password@ip:port/Index/Type/_search
    elastic-search-url: http://xxx.com/alias_rdebug_index/_doc/_search
    custom-commands:
        - DiPlugin\Command\SearchCommand
```