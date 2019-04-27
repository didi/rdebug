## ElasticSearch

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