<?php
/**
 * @author tanmingliang
 */

namespace Midi\Mock;

use Midi\Config;
use Midi\Container;

class MockDir
{
    /**
     * online deploy path redirect TO offline local path
     *
     * @param Config $config
     * @return array
     */
    public static function getRedirectDir(Config $config)
    {
        $redirect = $config->get('redirect-dir');
        if ($redirect === null) {
            $redirect = [];
        }

        $cwd = Container::make('workingDir');
        $deployPath = $config->get('php', 'deploy-path');
        if (!empty($deployPath) && $deployPath !== $cwd) {
            $redirect[$deployPath] = $cwd;
        }

        return $redirect;
    }
}