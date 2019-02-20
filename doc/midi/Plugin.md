## Plugin Midi

Midi 支持很多种方式扩展 midi 的行为。

首先，支持两个自定义行为：

- resolver 查找器
 
    查找器定义如何查找录制的流量，譬如命令的 -f 选项，提供对本地文件查找的支持。

    即 -f /path/to/xxx-session，即找到本地 /path/to/xxx-session 文件作为录制的流量进行回放。

    DiPlugin 提供一个 ElasticPlugin 实现，支持 -i，-o 选项对 elastic 进行搜索录制的流量。

- differ 对比
    
    对回放的结果和录制的数据进行对比，默认的实现只是对比响应的返回值。

- reporter 报告

    自定义生成报告内容。

这三个自定义行为只需要实现 `Midi\Differ\DifferInterface`、`Midi\Resolver\ResolverInterface` 和 `Midi\Reporter\ReporterInterface` 接口。

在配置里，把类名设置到 `session-resolver`、`differ` 和 `reporter` 配置项即可。

如果自定义行为的代码 和 phar 没有一起打包，需要在配置里设置下自动加载规则，使 phar 包能找到。

其次，Midi 提供插件和事件机制。支持两种类型插件，对应的配置项是 `preload-plugins` and `plugins`。

两种插件本质上，没有区别，只是加载时期不同。

### `preload-plugins`

在 console application 对象实例化之前，将加载此插件。

通过实现 `\Midi\Plugin\PreloadPluginInterface` 接口，即可实现此类插件。

此类插件，最主要的作用是订阅 `COMMAND_CONFIGURE` 事件，从而来自定义命令的选项。

### `plugins`

在 application 实例化后，执行 doRun 的时候，将加载此插件。

通过实现 `\Midi\Plugin\PluginInterface` 接口，即可实现此类插件。

### `Plugin events`

#### 事件接口

Midi 内部埋点大量的事件，插件可以实现 `\Midi\EventDispatcher\EventSubscriberInterface` 来订阅感兴趣的事件。

从而实现对 Midi 的扩展和控制。

#### 事件列表 

```
/**
 * The INIT event occurs after a Midi instance is done being initialized
 */
PluginEvents::INIT = 'init';

/**
 * The COMMAND_CONFIGURE event occurs command is configure and lets you plugin some arguments & options.
 */
PluginEvents::COMMAND_CONFIGURE = 'command-configure';

/**
 * The PRE_COMMAND_RUN event occurs before a command is executed and lets you modify the input arguments/options
 */
PluginEvents::PRE_COMMAND_RUN = 'pre-command-run';

/**
 * The POST_COMMAND_RUN event occurs after a command is executed.
 *
 */
PluginEvents::POST_COMMAND_RUN = 'post-command-run';

/**
 * The PRE_KOALA_START event occurs before koala start and you check some dependency.
 */
PluginEvents::PRE_KOALA_START = 'pre-start-koala';

/**
 * The PRE_SESSIONS_SOLVING event occurs before solving sessions.
 */
PluginEvents::PRE_SESSIONS_SOLVING = 'pre-sessions-solving';

/**
 * The POST_SESSIONS_SOLVING event occurs after solving sessions.
 */
PluginEvents::POST_SESSIONS_SOLVING = 'post-sessions-solving';

/**
 * The POST_PARSE_SESSION event occurs after parse recorded session.
 */
PluginEvents::POST_PARSE_SESSION = 'post-parse-session';

/**
 * The PRE_REPLAY_SESSION event occurs before replay one session.
 */
PluginEvents::PRE_REPLAY_SESSION = 'pre-replay-session';

/**
 * The POST_REPLAY_SESSION event occurs after replay one session.
 */
PluginEvents::POST_REPLAY_SESSION = 'post-replay-session';
```

## Example

DiPlugin 是一个滴滴的插件，同时包含 preload-plugin 和 plugin 两种插件。

DiPlugin 的 `DiPlugin\ElasticPlugin` 是一个 preload-plugin 插件，提供对 run 命令的扩充，支持对 elastic 搜索录制的流量。

DiPlugin 的 `DiPlugin\Plugin` 是一个 plugin 插件，主要是扩展和影响 Midi 的一些行为。


