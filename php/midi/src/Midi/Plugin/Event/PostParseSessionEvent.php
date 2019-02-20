<?php

/**
 * @author tanmingliang
 */

namespace Midi\Plugin\Event;

use Midi\Midi;
use Midi\EventDispatcher\Event;
use Midi\Koala\Replaying\CallFromInbound;

class PostParseSessionEvent extends Event
{
    protected $callFromInbound;
    protected $returnInbound;
    protected $actions;
    protected $mockFiles;
    protected $redirectDirs;

    /**
     * Constructor.
     *
     * @param string $name event name
     * @param Midi $midi
     * @param $callFromIndbound
     * @param $returnInbound
     * @param array $actions
     * @param array $mockFiles
     * @param array $redirectDirs
     * @param array $args
     */
    public function __construct(
        $name,
        $midi,
        $callFromIndbound,
        $returnInbound,
        $actions,
        $mockFiles,
        $redirectDirs,
        array $args = []
    ) {
        parent::__construct($name, $midi, $args);
        $this->callFromInbound = $callFromIndbound;
        $this->returnInbound = $returnInbound;
        $this->actions = $actions;
        $this->mockFiles = $mockFiles ?? [];
        $this->redirectDirs = $redirectDirs ?? [];
    }

    /**
     * @return CallFromInbound
     */
    public function getCallFromInbound()
    {
        return $this->callFromInbound;
    }

    public function setCallFromInbound($callFromInbound)
    {
        $this->callFromInbound = $callFromInbound;
    }

    public function getReturnInbound()
    {
        return $this->returnInbound;
    }

    public function setReturnInbound($returnInbound)
    {
        $this->returnInbound = $returnInbound;
    }

    public function getActions()
    {
        return $this->actions;
    }

    public function setActions($actions)
    {
        $this->actions = $actions;
    }

    public function getMockFiles()
    {
        return $this->mockFiles;
    }

    public function setMockFiles($mockFiles)
    {
        $this->mockFiles = $mockFiles;
    }

    public function getRedirectDirs()
    {
        return $this->redirectDirs;
    }

    public function setRedirectDirs($redirectDirs)
    {
        $this->redirectDirs = $redirectDirs;
    }
}
