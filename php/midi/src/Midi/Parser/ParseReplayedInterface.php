<?php
/**
 * @author tanmingliang
 */

namespace Midi\Parser;

use Closure;
use Midi\Koala\Replaying\ReplayingSession;
use Midi\Koala\Replayed\ReplayedSession;

interface ParseReplayedInterface
{
    public function setSendUPDParser(Closure $callable);

    public function setCallOutboundParser(Closure $callable);

    public function setAppendFileParser(Closure $callable);

    public function doParse(ReplayingSession $replayingSession, ReplayedSession $replayedSession, $parseType);
}