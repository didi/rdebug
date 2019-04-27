## ElasticSearch Config

Before the traffic is written to the ES,Need to set the data type for the `SessionId` field. Since the ES will set the field to `text` by default, When using `term` to search it, Will be caused analyze by '-' 

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

It is recommended to use ES index by alias
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