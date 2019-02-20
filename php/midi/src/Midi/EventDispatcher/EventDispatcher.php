<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace Midi\EventDispatcher;

use Midi\Midi;
use Midi\Plugin\Event\CommandEvent;
use Midi\Plugin\Event\PreCommandEvent;
use Midi\Plugin\Event\PostCommandEvent;
use Midi\Plugin\Event\SessionsSolvingEvent;
use Midi\Plugin\PluginEvents;
use Symfony\Component\EventDispatcher\EventDispatcher as BaseDispatcher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EventDispatcher extends BaseDispatcher
{

    /**
     * @var Midi
     */
    protected $midi;

    public function __construct(Midi $midi)
    {
        $this->midi = $midi;
    }

    public function dispatchEvent(string $name, array $args = array())
    {
        $this->dispatch($name, new Event($name, $this->midi, $args));
    }

    public function dispatchCommandEvent(
        string $name,
        string $commandName,
        InputInterface $input,
        OutputInterface $output,
        array $args = []
    ) {
        if ($name === PluginEvents::PRE_COMMAND_RUN) {
            $event = new PreCommandEvent($name, $commandName, $this->midi, $input, $output, $args);
        } elseif ($name === PluginEvents::POST_COMMAND_RUN) {
            $event = new PostCommandEvent($name, $commandName, $this->midi, $input, $output, $args);
        } else {
            $event = new CommandEvent($name, $commandName, $this->midi, $input, $output, $args);
        }
        $this->dispatch($name, $event);
    }

    public function dispatchSolving(string $name, InputInterface $input, OutputInterface $output, array $args = array())
    {
        $this->dispatch($name, new SessionsSolvingEvent($name, $this->midi, $input, $output, $args));
    }
}