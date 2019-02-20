<?php

/**
 * @author tanmingliang
 */

namespace DiPlugin\Mock;

use DiPlugin\DiConfig;
use DiPlugin\Module;
use Midi\Container;

/**
 * For ease of use, we auto generate project's mock dirs if project had config in @see \DiPlugin\Module
 */
class MockDir
{
    public static function getRedirectDir()
    {
        if (!DiConfig::isExistModuleConfig()) {
            return [];
        }

        // auto generate mock dirs by project's deploy path and log path...
        $cwd = Container::make('workingDir');
        $logDir = Container::make('logDir');
        $bizConfigDir = Container::make('bizConfigDir');
        $ciSystemDir = Container::make('ciSystemDir');
        $moduleConfig = DiConfig::getModuleConfig();

        $default = [
            $moduleConfig['deploy']        => $cwd,
            $moduleConfig['log']           => $logDir,
            Module::DEPLOY_SYSTEM_PATH     => $ciSystemDir,
            Module::DEPLOY_BIZ_CONFIG_PATH => $bizConfigDir,
            '/home/xiaoju/.services/disf'  => $cwd . '/__naming__',
        ];

        // fix for fastdev2.0 ln -s /home/xiaoju to /Users/didi/xiaoju
        $fastDev2deploy = str_replace('/home/xiaoju/', '/Users/didi/xiaoju/', $moduleConfig['deploy']);
        if (strpos($fastDev2deploy, $cwd) !== false) {
            // at fastdev2.0 path
            return self::fixForFastdev2($moduleConfig, $logDir, $ciSystemDir, $fastDev2deploy, $cwd);
        } elseif (is_dir('/Users/didi/xiaoju/webroot/gulfstream/application')) {
            // fix for fastdev2.0 ln -s /home/xiaoju to /Users/didi/xiaoju
            foreach ($default as $path => $defaultPath) {
                $fixFastDev2 = str_replace('/home/xiaoju/', '/Users/didi/xiaoju/', $path);
                $default[$fixFastDev2] = $defaultPath;
            }
        }

        return $default;
    }

    private static function fixForFastdev2($config, $logDir, $ciSystemDir, $fastDev2deploy, $cwd)
    {
        $default = [
            $fastDev2deploy                     => $cwd,
            $config['log']                      => $logDir,
            '/Users/didi/xiaoju/.services/disf' => $cwd . '/__naming__',
        ];

        $fastDev2System = '/Users/didi/xiaoju/webroot/gulfstream/application/system';
        if (!is_dir($fastDev2System)) {
            $fastDev2System = $ciSystemDir;
        }
        $default[Module::DEPLOY_SYSTEM_PATH] = $fastDev2System;

        $fastDev2BizConfigDir = '/Users/didi/xiaoju/webroot/gulfstream/application/biz-config';
        $default[Module::DEPLOY_BIZ_CONFIG_PATH] = $fastDev2BizConfigDir;

        return $default;
    }
}
