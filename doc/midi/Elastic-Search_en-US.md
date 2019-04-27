## ElasticSearch

### create index
Before the traffic is written to the ES,Need to set the data type for the `SessionId` field. Since the ES will set the field to `text` by default, When using `term` to search it, Will be caused analyze by `-`

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

### use alias
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

### set midi config
In config.yml, add an extension command
```
custom-commands:
    - DiPlugin\Command\DoctorCommand
    - DiPlugin\Command\InitCommand
    - DiPlugin\Command\SearchCommand
```
Set ES search URL
```
# for elastic
preload-plugins:
    - Midi\ElasticPlugin
session-resolver: Midi\Resolver\ElasticResolver

# set your elastic search url
# http://username:password@ip:port/Index/Type/_search
elastic-search-url: http://xxx.com/alias_rdebug_index/_doc/_search
```