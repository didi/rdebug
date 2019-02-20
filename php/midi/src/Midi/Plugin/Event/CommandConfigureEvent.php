<?php
/**
 * @author tanmingliang
 */

namespace Midi\Plugin\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Console\Command\Command;

class CommandConfigureEvent extends Event
{

    /**
     * @var Command
     */
    protected $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function addArgument($name, $mode = null, $description = '', $default = null)
    {
        $this->command->addArgument($name, $mode, $description, $default);
        return $this;
    }

    public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        $this->command->addOption($name, $shortcut, $mode, $description, $default);
        return $this;
    }

    public function getName()
    {
        return $this->command->getName();
    }

    public function setDescription($description)
    {
        $this->command->setDescription($description);
        return $this;
    }
}