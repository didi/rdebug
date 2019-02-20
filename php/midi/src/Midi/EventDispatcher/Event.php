<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace Midi\EventDispatcher;

use Midi\Midi;
use Symfony\Component\EventDispatcher\Event as BaseEvent;

/**
 * Midi base event class
 */
class Event extends BaseEvent
{
    /**
     * @var Midi
     */
    protected $midi;

    /**
     * @var string event's name
     */
    protected $name;

    /**
     * @var array Arguments
     */
    protected $args;

    /**
     * Constructor.
     *
     * @param string $name The event name
     * @param Midi $midi
     * @param array $args Arguments
     */
    public function __construct($name, $midi, array $args = array())
    {
        $this->name = $name;
        $this->midi = $midi;
        $this->args = $args;
    }

    /**
     * Returns the event's name.
     *
     * @return string The event name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the event's arguments.
     *
     * @return array The event arguments
     */
    public function getArguments()
    {
        return $this->args;
    }

    /**
     * Returns the event's midi.
     *
     * @return Midi The event midi
     */
    public function getMidi()
    {
        return $this->midi;
    }
}
