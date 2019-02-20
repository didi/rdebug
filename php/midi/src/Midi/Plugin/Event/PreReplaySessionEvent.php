<?php

/**
 * @author tanmingliang
 */

namespace Midi\Plugin\Event;

use Symfony\Component\EventDispatcher\Event;
use Midi\Koala\Replaying\ReplayingSession;

class PreReplaySessionEvent extends Event
{
    /**
     * @var ReplayingSession
     */
    private $replayingSession;

    /**
     * Constructor.
     *
     * @param $replayingSession ReplayingSession
     */
    public function __construct(ReplayingSession $replayingSession)
    {
        $this->replayingSession = $replayingSession;
    }

    /**
     * Returns the replaying session
     *
     * @return ReplayingSession
     */
    public function getReplayingSession()
    {
        return $this->replayingSession;
    }

    public function setReplayingSession($replayingSession)
    {
        $this->replayingSession = $replayingSession;
    }
}
