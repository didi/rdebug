<?php

/**
 * @author tanmingliang
 */

namespace Midi\Plugin\Event;

use Symfony\Component\EventDispatcher\Event;
use Midi\Koala\Replayed\ReplayedSession;

class PostReplaySessionEvent extends Event
{
    /**
     * @var ReplayedSession
     */
    private $replayedSession;

    /**
     * @var array
     */
    private $args;

    /**
     * Constructor.
     *
     * @param $replayedSession ReplayedSession
     * @param array $args
     */
    public function __construct(ReplayedSession $replayedSession, $args = [])
    {
        $this->replayedSession = $replayedSession;
        $this->args = $args;
    }

    /**
     * Returns the replayed session
     *
     * @return ReplayedSession
     */
    public function getReplayedSession()
    {
        return $this->replayedSession;
    }

    /**
     * @param ReplayedSession $replayedSession
     */
    public function setReplayedSession(ReplayedSession $replayedSession)
    {
        $this->replayedSession = $replayedSession;
    }

    /**
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }
}
