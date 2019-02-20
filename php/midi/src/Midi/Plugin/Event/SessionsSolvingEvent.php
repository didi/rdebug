<?php

namespace Midi\Plugin\Event;

use Midi\Midi;
use Midi\EventDispatcher\Event;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SessionsSolvingEvent extends Event
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * Constructor.
     *
     * @param string $name event name
     * @param Midi $midi
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $args Arguments
     */
    public function __construct($name, $midi, $input, $output, array $args = array())
    {
        parent::__construct($name, $midi, $args);
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * Returns the command input interface
     *
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Retrieves the command output interface
     *
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }
}
