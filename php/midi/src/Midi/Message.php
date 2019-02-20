<?php declare(strict_types=1);

namespace Midi;

class Message
{
    const SUMMARY_DO_REPLAY_NO_DIFF = <<<EOF
<info>Summary: Replayed <comment>%d</comment> Sessions <comment>No DIFF</comment>. Spent <comment>%d</comment> s%s</info>

EOF;

    const SUMMARY_DO_REPLAY_EXIST_DIFF = <<<EOF
<info>Summary: Replayed <comment>%d</comment> Sessions <error>%d</error> DIFFERENT. Spent <comment>%d</comment> s%s</info>
<info>Diff sessionId: <error>%s</error></info>
<info>Retry failed session: <comment>midi run -s %s</comment></info>
EOF;

    const SUMMARY_NO_REPLAY = '<info>Summary: Replayed <comment>0</comment> Sessions. Spent <comment>%d</comment> s%s</info>';

    // run command
    const RUN_COMMAND_INVALID_PARAMS = '<error>Require files, use `-f /path/to/session.json`, get more usage `midi help run`.</error>';

    const RUN_COMMAND_ELASTIC_INVALID_PARAMS = '<error>Require uri or sessionId or file, get usage `midi help run`.</error>';

    const RUN_COMMAND_NOT_FOUND_XDEBUG = '<error>Can not find `xdebug` extension, -x option will no work.</error>';

    const RUN_COMMAND_NOT_FOUND_COVERAGE = '<error>Can not find `xdebug` extension, -C option will no work.</error>';

    const RUN_COMMAND_NOT_FOUND_TRACE = '<error>Can not find `xdebug` extension, -T option will no work.</error>';

    const RUN_COMMAND_NO_SESSION_REPLAY = '<comment>No sessions for test.</comment>';

    const RUN_COMMAND_REPLAYER_NOT_START = '<error>Replayer not start up, try later.</error>';
}
