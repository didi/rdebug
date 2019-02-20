<?php

namespace Midi\Plugin;

/**
 * The Plugin Events.
 *
 * @author tanmingliang
 */
class PluginEvents
{
    /**
     * The INIT event occurs after a Midi instance is done being initialized
     *
     * @var string
     */
    const INIT = 'init';

    /**
     * The COMMAND_CONFIGURE event occurs command is configure and lets you plugin some arguments & options.
     *
     * @var string
     */
    const COMMAND_CONFIGURE = 'command-configure';

    /**
     * The PRE_COMMAND_RUN event occurs before a command is executed and lets you modify the input arguments/options
     *
     * @var string
     */
    const PRE_COMMAND_RUN = 'pre-command-run';

    /**
     * The POST_COMMAND_RUN event occurs after a command is executed.
     *
     * @var string
     */
    const POST_COMMAND_RUN = 'post-command-run';

    /**
     * The PRE_KOALA_START event occurs before koala start and you check some dependency.
     *
     * @var string
     */
    const PRE_KOALA_START = 'pre-start-koala';

    /**
     * The PRE_SESSIONS_SOLVING event occurs before solving sessions.
     *
     * @var string
     */
    const PRE_SESSIONS_SOLVING = 'pre-sessions-solving';

    /**
     * The POST_SESSIONS_SOLVING event occurs after solving sessions.
     *
     * @var string
     */
    const POST_SESSIONS_SOLVING = 'post-sessions-solving';

    /**
     * The POST_PARSE_SESSION event occurs after parse recorded session.
     *
     * @var string
     */
    const POST_PARSE_SESSION = 'post-parse-session';

    /**
     * The PRE_REPLAY_SESSION event occurs before replay one session.
     *
     * @var string
     */
    const PRE_REPLAY_SESSION = 'pre-replay-session';

    /**
     * The POST_REPLAY_SESSION event occurs after replay one session.
     *
     * @var string
     */
    const POST_REPLAY_SESSION = 'post-replay-session';
}
