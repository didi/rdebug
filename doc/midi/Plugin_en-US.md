## Plugin Midi

Midi support many ways to plugin midi's actions.

You could implement your session resolver by `session-resolver` config.

Resolver used to implement how to find record session. 

You could implement your differ by `differ` config.

Differ used to diff online record session with offline replayed session.

You could custom your replay report by implement `Midi\Reporter\ReporterInterface` and confiy by `reporter` options.

Midi also support two different plugin with events: `preload-plugins` and `plugins`.

Both plugins could config by config.yml. The only different between two plugins is the loads period.

### `preload-plugins`

Preload plugins will be load before console application instance.

So, you could subscribe `COMMAND_CONFIGURE` event, which could add some options to command.

This plugin need implement `\Midi\Plugin\PreloadPluginInterface` interface.

### `plugins`

Plugins will be load when application call `doRun`.

This plugin need implement `\Midi\Plugin\PluginInterface` interface.

### `Plugin events`

#### Interface

Plugin want to subscribe some events, plugin must implement `\Midi\EventDispatcher\EventSubscriberInterface` interface.

#### Support Events

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

DiPlugin is a example of preload-plugin and plugin.

DiPlugin provide `DiPlugin\ElasticPlugin` preload plugin which add some option to run command and support search session from elastic.

DiPlugin provide `DiPlugin\Plugin` plugin which will affect midi's actions. 

