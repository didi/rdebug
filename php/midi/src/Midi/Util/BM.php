<?php

namespace Midi\Util;

use Midi\Container;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Benchmark
 */
class BM
{
    const START_REPLAYER = 'Start replayer';
    const RESOLVE_SESSIONS = 'Resolve sessions';
    const REPLAYED_SESSION = 'Replayed session';

    /** @var Stopwatch */
    private static $stopWatch;

    /** @var OutputInterface */
    private static $output;

    public static function init()
    {
        static $init = false;
        if ($init) {
            return;
        }
        $init = true;
        self::$stopWatch = Container::make('stopWatch');
        self::$output = Container::make('output');
    }

    public static function start($name)
    {
        self::init();
        self::$stopWatch->start($name);
    }

    public static function stop($name, $verbosity = OutputInterface::VERBOSITY_VERBOSE)
    {
        self::init();
        $event = self::$stopWatch->stop($name);
        $duration = $event->getDuration();
        self::$output->writeln("<info>$name spent $duration ms</info>", $verbosity);
        return $duration;
    }
}
