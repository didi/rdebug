<?php

namespace Midi\Plugin\Event;

use Midi\Midi;
use Midi\EventDispatcher\Event;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandEvent extends Event
{
    /**
     * @var string
     */
    private $commandName;

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
     * @param string $commandName command name
     * @param Midi $midi
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $args Arguments
     */
    public function __construct($name, $commandName, $midi, $input, $output, array $args = array())
    {
        parent::__construct($name, $midi, $args);
        $this->commandName = $commandName;
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

    /**
     * Retrieves the name of the command being run
     *
     * @return string
     */
    public function getCommandName()
    {
        return $this->commandName;
    }
}
