## Config.yml

Midi 支持 3 个配置来控制和扩展 Midi 的行为。

- 默认配置，在源码 `midi/src/Midi/Config.yml` 里，将会一起打包到 phar 文件里

- 项目级配置，在项目的根目录下 `/path/to/your_project/.midi/config.yml`

- 全局配置，在用户目录下 `~/.midi/config.yml`

Midi 会将三个配置合并（如果存在的话），优先级是: 项目级配置 > 全局配置 > 默认配置。

通常情况下，默认配置已经足够，除非有一些定制的调整，可以通过 项目级 和 全局配置 来调整。

## 配置项

下面是一个简单的示例：

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

# For DiPlugin Search Command with context
record-host:

php:
    deploy-path:
    koala-php-ini:
        memory_limit: 1G
        error_log: /tmp/midi/logs/php-error.log
    pre-inject-file:
    session-resolver:
#        Midi\Resolver\FileResolver
        Midi\Resolver\ElasticResolver
#        DiPlugin\Resolver\ElasticResolver
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

    # For DiPlugin Only
    module-name:
    enable-disf: false
    module-disf-name:
    enable-uploader: 1
    uploader-url:
    recommend-dsl-url:
```

## 配置注解

- `rdebug-dir`

设置 rdebug 输出目录。

此目录会存放一些文件和中间结果，譬如回放的 replayer.so 会从 phar 包中拷贝到此目录下。存放生成的报告、数据、静态文件等。

默认的目录是 `/tmp/midi/`。

子目录结构如下：

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

    控制 Koala 行为的配置。

    - inbound-port

        设置 inbound 端口号，默认 5514

    - sub-port

        设置 system under test 端口号，即回放时起的 php -S 服务的端口号，默认值是 5515

    - outbound-port

        设置 outbound 端口号, 默认是 5516

    - bypass-ports
    
        这里列举的端口号，koala 会直接放过，不会拦截到 outbound。

    - replay-match-threshold
      
        outbound 相识度匹配的阈值，当线下回放的流量和线上录制的流量，进行相识度匹配，只有超过这个阈值，才认为匹配上。

        最终，会取匹配上 & 相识度最大的流量返回。

        阈值默认是 0.75，即 75%。

    - inbound-read-timeout
    
        回放时，midi 发送录制的流量给 inbound，inbound 发送 sut 进行回放。

        这个配置是设置 inbound 从 sut 读取响应的超时时间。

        当进行 Xdebug 单步调试的时候，特别有用，默认值是 86400s。
    
    - gc-global-status-timeout

        koala 内部会进行一些垃圾回收，这个配置就是设置垃圾回收的时间。

        当进行 Xdebug 单步调试的时候，特别有用，默认值是 86400s。

    - inbound-protocol
    
        inbound 接受录制的流量的协议，默认是 HTTP。


- redirect-dir
    
    目录重定向，常用于 线上部署路径 和 本地回放路径不一致时，通过设置 部署路径 和 回放路径的映射关系，从而实现目录重定向。

    这样，在线下回放的时候，代码可以放在任何位置，不需要按照线上的部署路径，存放代码。
    
    如果你代码中，有类似 include/require 某线上路径。但本地回放的路径 和 线上路径不一致，把线上和本地的路径映射关系配置到此处。

- mock-files
    
    有时候想 Mock 一些文件的内容，Koala 也支持对文件的 Mock。

    配置格式是 文件名: base64_encode(mock content).

- record-host

    DiPlugin 插件搜索命令使用，传递机器名，搜索录制的流量更准确

- php

    php 相关的配置。

    - deploy-path
      
        模块的部署路径。当部署路径和本地回放路径不一致的时候，且需要生成覆盖率报告的时候，需要设置这个值为部署路径。

    - koala-php-ini
      
        SUT 服务给 php -S 定义 php 配置项

    - pre-inject-file

        在代码执行之前，注入的代码，通过 auto_prepend 的方式注入到 php -S

    - session-resolver
      
        流量的查找器，譬如指定文件回放时，查找器会去找本地文件。

        Midi 默认提供两个实现：`Midi\Resolver\FileResolver` 和 `Midi\Resolver\ElasticResolver`，分别是去查找本地 session 文件 和 去 Elastic 搜索 session。

        DiPlugin 提供 `DiPlugin\Resolver\ElasticResolver`，做了一些增量的功能。

        它们见的关系是：`DiPlugin\Resolver\ElasticResolver` extends `Midi\Resolver\ElasticResolver` extends `Midi\Resolver\FileResolver`.

        也可以实现你自定义的查找器，把类名配置到此配置项即可。注意，如果自定义的查找器代码不在 phar 包里，需要设置自动加载，让 autoloader 能找到这个类。

        自动加载相关的设置，见下文。

    - differ
    
        比较器，默认的比较器是比较 线上录制下来的响应 和 本地回放的响应。

        也可以自定义比较器，将类名配置到此配置项即可。注意，自动加载。

    - reporter

        生成 HTML 报告的实现，默认是 `Midi\Reporter\Reporter`

        也可自定义生成报告的实现，只需要实现 `Midi\Reporter\ReportInterface` 接口。

    - preload-plugins
    
        提前加载插件，和下文的 plugins 插件没太大的区别，只是插件被加载的时期不一样。

        preload plugin 会在 symfony console application 被实例化之前，就加载了。

        这样，插件就可以订阅一些事件，来修改和扩展命令的行为。

        譬如 `DiPlugin\ElasticPlugin` 插件，会扩展 run 命令，增加一些 -i，-o 等选项，支持对 elastic 搜索查询。

    - plugins
      
        同 preload-plugin，只是加载时机不一样。从而能订阅的事件也不一样。

        这个插件在 application 实例化后，执行 doRun 方法时，注册进来。

        譬如，DiPlugin\Plugin 插件。

    - custom-commands

        Midi 支持扩展自定义命令，从而丰富 Midi 支持的命令。

        譬如 `DiPlugin\Command\SearchCommand` 就是一个示例，提供搜索名，支持对 elastic 搜索。

    - elastic-search-url

        DiPlugin 的 ElasticPlugin 扩展使用，这个 Url 是去 Elastic 查询的 Url，用来到 Elastic 查询录制流量的。

    - autoloader
     
        通过配置，丰富自动加载规则。

        当扩展 Midi Phar 包的时候，扩展的代码 和 Phar 包不是在一起的。需要在此处把扩展的代码加到自动加载规则里。这样 Midi 就能访问到这些扩展类。

        支持自定义加载的规则有 psr-0，psr-4，classmap。

    - module-name

        DiPlugin 私有属性，设置模块名。

    - enable-disf

        DiPlugin 私有属性，是否开启 Disf 服务发现。

        如果模块未使用的话，设置 false 忽略 disf。

    - module-disf-name

        DiPlugin 私有属性，开启服务发现后，通过这个属性设置当前模块的 Disf 服务发现名称。

    - enable-uploader & uploader-url

        DiPlugin 私有属性，用来控制是否开启上报功能的开关，默认会把执行的命令和结果上报到 uploader-url 指定的 Url。

        如果不需要上报，可以关闭这个功能。

    - recommend-dsl-url
    
        DiPlugin 私有属性，获取 Nuwa 平台推荐的 Session 的 Url，可以忽略这个选项。


