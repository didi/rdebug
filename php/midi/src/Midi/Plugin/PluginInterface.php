<?php

namespace Midi\Plugin;

use Midi\Midi;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Plugin interface
 *
 * activate is called after Application instance.
 *
 * Something like Composer plugin.
 */
interface PluginInterface
{
    /**
     * Version number of the plugin api version
     *
     * @var string
     */
    const PLUGIN_API_VERSION = '1.0.0';

    /**
     * Apply plugin modifications to Composer
     *
     * @param Midi $composer
     */
    public function activate(Midi $midi, InputInterface $input, OutputInterface $output);
}
