<?php

namespace Midi\Plugin;

use Midi\Midi;

/**
 * Preload Plugin interface.
 *
 * activate called before Application instance.
 *
 * eg: you could listen command configure event.
 *
 * Something like Composer plugin.
 */
interface PreloadPluginInterface
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
    public function activate(Midi $midi);
}
