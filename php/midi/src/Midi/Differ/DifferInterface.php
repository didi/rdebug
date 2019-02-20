<?php

namespace Midi\Differ;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Midi\Koala\Replayed\ReplayedSession;
use Midi\Koala\Replaying\ReplayingSession;

/**
 * differ record session & replayed session
 */
interface DifferInterface
{
    public function setOptions(array $options, InputInterface $input = null, OutputInterface $output = null);

    public function diff(ReplayingSession $replayingSession, ReplayedSession $replayedSession);
}