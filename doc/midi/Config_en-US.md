## Config.yml

Midi support three config files to control midi's action.

- default config, at `midi/src/Midi/Config.yml`, which will be package into phar.
- project config, at `/path/to/your_project/.midi/config.yml`
- global config, at `~/.midi/config.yml`

Default config is enough. If you need something different, you could change it at your project or global config.

## Support Config

example config:

```yml
rdebug-dir: /tmp/midi/

koala:
    inbound-port: 5514
    sub-port: 5515
    outbound-port: 5516
    bypass-ports:
        - 9666
    replay-match-threshold: 0.75
    inbound-read-timeout: 86400s
    gc-global-status-timeout: 86400s
    inbound-protocol: HTTP # HTTP or FastCGI, default is HTTP

redirect-dir:
    # /path/to/deploy/dir : /path/to/local/dir

mock-files:
    # mock filename content
    # /path/to/need/mock/filename: base64_encode(your-mock-content)

# Used by DiPlugin's Search Command, search elastic with hostname
record-host:

php:
    bin:
    deploy-path:
    koala-php-ini:
        memory_limit: 1G
        error_log: /tmp/midi/logs/php-error.log
    pre-inject-file:
    session-resolver: DiPlugin\Resolver\ElasticResolver
    differ:
    reporter: Midi\Reporter\Reporter  # Generate HTML report
    preload-plugins:
        - DiPlugin\ElasticPlugin
    plugins:
        - DiPlugin\Plugin
    custom-commands:
        - DiPlugin\Command\DoctorCommand
        - DiPlugin\Command\InitCommand
        - DiPlugin\Command\SearchCommand
    elastic-search-url:
    autoloader:
        psr-0:
        psr-4:
        classmap:
        #    - MyNamespace\MyClass: '/path/to/MyClass.php'

    # For DiPlugin
    module-name:
    enable-disf: false
    module-disf-name:
    enable-uploader: 1
    uploader-url:
    recommend-dsl-url:
    sync-module-url: 
```

- `rdebug-dir`

rdebug's output dirs and store some file copy from phar.

```
rdebug
   - res
     - replayer    store koala-replayer.so
     - depends     midi's depends dir
     - static      midi's static files, eg: report static files
   - session       store session files, which resolver from elastic
   - mock          store some php mock code, eg: prepend.php
   - log           store php-error.log and your project's logs
   - upgrade       used when upgrade
   - report
    - coverage     store xdebug coverage data
    - trace        store xdebug trace data
```

- koala

    Configs for koala.

    - inbound-port

        Set inbound port, default is 5514

    - sub-port

        Set system under test port, default is 5515

    - outbound-port

        Set outbound port, default is 5516

    - bypass-ports

        Bypass ports which koala will not redirect to outbound. default is empty and all connect will redirect to outbound.

    - replay-match-threshold

        Outbound replay traffic use similarity matcher, this config control similarity threshold. default is 0.75.
      
        When traffic similarity reach 75% and the max similarity record traffic will return.

    - inbound-read-timeout
    
        Set inbound read timeout from sut. when use midi with xdebug, recommand set this config with a large timeout. default is 86400s.
    
    - gc-global-status-timeout

        Set koala gc global status timeout. when use midi with xdebug, recommand set this config with a large timeout. default is 86400s.

    - inbound-protocol
    
        Inbound receive record session, which protocol default is HTTP

- redirect-dir

    Online deploy path different with offline replay path (offline code path different with online)
    
    You could set redirect dir from deploy dir to local dir.

- mock-files

    You could mock file contents.

    Key is file path, value is base64_encode of your mock content.

- record-host
    
    The recorder machine name.
     
    Used by DiPlugin search command, pass the machine name, search for recorded traffic more accurately.

- php
    
    - bin
    
        php binary file used by replayer as server.
        
    - deploy-path
      
        Set your online deploy path. Midi will auto redirect your deploy path to your local project path.

    - koala-php-ini
    
        Support set php ini for koala php -S server.

    - pre-inject-file

        Pre inject file which will be auto_prepend to php -S.

    - session-resolver
    
        You could implemets your session resolver, default support resolver from file & elastic.
  
        Default is `Midi\Resolver\ElasticResolver`.

        `Midi\Resolver\ElasticResolver` extends `Midi\Resolver\FileResolver`.

    - differ
    
        You could implements your differ. which will diff replayedSesssion with recordSession.

    - reporter

        Generate HTML reporter, default is `Midi\Reporter\Reporter`

        You could custom your reporter and implement `Midi\Reporter\ReportInterface` interface.

    - preload-plugins
    
        Plugin support, preload plugin will be load before application instance.

        You could register preload plugins and modify commands.

        Default preload plugin is `DiPlugin\ElasticPlugin`, which will register some option for run command.

    - plugins
    
        Plugins support, default

        - DiPlugin\Plugin

    - custom-commands
    
        You could register your custom commands to midi.

    - elastic-search-url

        Used by `Midi\Resolver\ElasticResolver` support resolver session from elastic.

        This config is elastic search url.

    - autoloader
      
        Autoloader support for register custom autoloader rules.

        Support psr-0, psr-4, classmap.
      
        Eg: add some custom command and add command class to autoloader, so midi could find your custom command class.

    - module-name

        DiPlugin private config. Just set module's name. You could ignore.

    - enable-disf

        DiPlugin private config. Whether enable disf. You could ignore.

    - module-disf-name

        DiPlugin private config. If enable disf, this config is set your module's disf name.

    - enable-uploader & uploader-url

        DiPlugin private config. Enable uploader will upload some replay relate data.

    - recommend-dsl-url

        DiPlugin private config.
        
    - sync-module-url
        
        DiPlugin private config. Use this url to sync module config.

