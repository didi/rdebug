<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace Midi\Command;

use Midi\Midi;
use Midi\Console\Application;
use Midi\Plugin\PluginEvents;
use Midi\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->getEventDispatcher()->dispatchCommandEvent(
            PluginEvents::PRE_COMMAND_RUN, $this->getName(), $input, $output
        );

        parent::initialize($input, $output);
    }

    /**
     * @return Midi
     */
    protected function getMidi()
    {
        /** @var Application $app */
        $app = $this->getApplication();

        return $app->getMidi();
    }

    /**
     * @return EventDispatcher
     */
    protected function getEventDispatcher()
    {
        return $this->getMidi()->getEventDispatcher();
    }
}