<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace Midi;

use Midi\Plugin\PreloadPluginInterface;
use Midi\Plugin\PluginEvents;
use Midi\EventDispatcher\EventSubscriberInterface;
use Midi\Plugin\Event\CommandConfigureEvent;
use Midi\Resolver\ElasticResolver;

/**
 * Elastic preload plugin could subscriber command configure event and you could plugin some args & options to command.
 */
class ElasticPlugin implements PreloadPluginInterface, EventSubscriberInterface
{

    public function activate(Midi $midi)
    {
    }

    public static function getSubscribedEvents()
    {
        return array(
            PluginEvents::COMMAND_CONFIGURE => 'onCommandConfigure',
        );
    }

    public static function onCommandConfigure(CommandConfigureEvent $event)
    {
        ElasticResolver::onCommandConfigure($event);
    }
}