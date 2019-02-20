# 指定文件回放

本文将介绍回放本地文件的方式。

## RUN 命令

Run 命令支持的选项如下：

```
$ ./midi.phar help run
Usage:
  run [options]
  replay

Options:
  -f, --file=FILE                              Replay session by file (multiple values allowed)
  -x, --xdebug                                 Replay with xdebug, you could set breakpoint when replay
  -R, --report                                 Generate replay report
  -T, --trace                                  Generate Xdebug function traces
  -C, --coverage                               Generate code coverage report
  -O, --open                                   After replayed, direct open report at browser
  -M, --match-strategy[=MATCH-STRATEGY]        Set replay match strategy for traffic, support: `chunk` or `sim` [default: "sim"]
  -E, --exclude-key[=EXCLUDE-KEY]              Ignore some different value of keys, eg: -E 'abc,efg' [default: false]
  -D, --display-diff-only[=DISPLAY-DIFF-ONLY]  Display different value only, default display all output to console [default: false]
  -i, --request-in=REQUEST-IN                  Replay by request URI or request input keywords (multiple values allowed)
  -s, --sessionId=SESSIONID                    Replay by session id (multiple values allowed)
  -S, --save[=SAVE]                            Save replayed session to file [default: false]
  -c, --count=COUNT                            Replay session count [default: 1]
  -h, --help                                   Display this help message
  -q, --quiet                                  Do not output any message
  -V, --version                                Display this application version
      --ansi                                   Force ANSI output
      --no-ansi                                Disable ANSI output
  -n, --no-interaction                         Do not ask any interactive question
  -v|vv|vvv, --verbose                         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  php midi.phar run -f session.json, use `php midi run -h` to see more...
```

## 模块信息

假设我们有一个线上服务，称之为 HermesAPI。

代码部署路径为：`/home/xiaoju/webroot/gulfstream/application/hermesapi/v1`。

HermesAPI 模块有两个代码依赖，譬如 biz-config 配置 和 system 基础库。对应的部署路径分别是：

biz-config: `/home/xiaoju/webroot/gulfstream/application/biz-config`

system: `/home/xiaoju/webroot/gulfstream/application/system`

## 回放

假设，我们已经在线上录制 HermesAPI 模块的流量，并存储到文件。现在，我们在 macOS 本地进行回放。

假设 HermesAPI 在本地的路径是：`/Users/didi/DiDiCode/hermesAPI`

biz-config 在本地的路径是：`/Users/didi/DiDiCode/system`

system 在本地的路径是：`/Users/didi/DiDiCode/biz-config`

那么，我们只需要在 `/Users/didi/DiDiCode/hermesAPI` 目录下创建 `.midi/config.yml` 配置，内容如下：

```yml
redirect-dir:
    /home/xiaoju/webroot/gulfstream/application/hermesapi/v1: /Users/didi/DiDiCode/hermesAPI
    /home/xiaoju/webroot/gulfstream/application/system: /Users/didi/DiDiCode/system
    /home/xiaoju/webroot/gulfstream/application/biz-config: /Users/didi/DiDiCode/biz-config
php:
    deploy-path: /home/xiaoju/webroot/gulfstream/application/hermesapi/v1
```

即可回放流量：

```
$ cd /Users/didi/DiDiCode/hermesAPI
$ midi.phar run -f /path/to/record-session

# or
$ midi.phar run -f /path/to/record-session -ORTC  # 生成 回放报告、Trace 报告、覆盖率报告
```

如果你想在回放流量之前，执行一段代码，可以通过如下方式实现，在 `.midi/config.yml` 增加如下配置：

```
php:
    pre-inject-file: /Users/didi/DiDiCode/hermesAPI/.midi/inject.php
```

把你想要提前执行的代码，写入到 `/Users/didi/DiDiCode/hermesAPI/.midi/inject.php` 文件里。

