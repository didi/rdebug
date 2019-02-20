<?php

namespace DiPlugin;

use Midi\Container;
use Midi\Exception\Exception;
use Symfony\Component\Finder\Finder;

final class DiConfig
{
    const CI = 'ci';
    const NUWA = 'nuwa';
    const PREG_GET_MODULE_NAME = '/\\bdefine\\(\\s*("(?:[^"\\\\]+|\\\\(?:\\\\\\\\)*.)*"|\'(?:[^\'\\\\]+|\\\\(?:\\\\\\\\)*.)*\')\\s*,\\s*("(?:[^"\\\\]+|\\\\(?:\\\\\\\\)*.)*"|\'(?:[^\'\\\\]+|\\\\(?:\\\\\\\\)*.)*\')\\s*\\);/is';

    private static $moduleName;
    private static $framework;

    private static function getMidiConfig()
    {
        static $midiConfig;
        if ($midiConfig === null) {
            $midiConfig = Container::make('config');
        }
        return $midiConfig;
    }

    /**
     * Is exist module config.
     *
     * @param string|null $moduleName
     * @return bool
     */
    public static function isExistModuleConfig(string $moduleName = null)
    {
        if (empty($moduleName)) {
            $moduleName = self::getModuleName();
            if (empty($moduleName)) {
                return false;
            }
        }

        if (!empty(Module::MODULE[$moduleName])) {
            return true;
        }
        return false;
    }

    /**
     * @param string $module
     * @return array
     * @throws Exception
     */
    public static function getModuleConfig($module = null)
    {
        static $moduleConfig = null;
        if ($moduleConfig) {
            return $moduleConfig;
        }

        if ($module === null && !($module = static::getModuleName())) {
            throw new Exception("<error>Can not find MODULE_NAME from source.</error>");
        }

        if (!isset(Module::MODULE[$module])) {
            throw new Exception("<error>Sorry, not support module $module now.</error>");
        }

        return $moduleConfig = Module::MODULE[$module];
    }

    /**
     * Get current project's module name
     */
    public static function getModuleName()
    {
        if (static::$moduleName) {
            return static::$moduleName;
        }

        $name = self::getMidiConfig()->get('php', 'module-name');
        if (!empty($name)) {
            return static::$moduleName = $name;
        }

        $dir = Container::make('workingDir');

        $finder = new Finder();
        $finder->in($dir)->name('index.php')->depth(0);
        foreach ($finder as $file) {
            $filename = $file->getPathname();
            $contents = file_get_contents($filename);
            if (strpos($contents, 'NUWA_START')) {
                /* for nuwa */
                $filename = $file->getPath() . '/config/constants.php';
            }
            if ($handle = fopen($filename, "r")) {
                while (!feof($handle)) {
                    $line = fgets($handle);
                    if (strpos(trim($line), 'MODULE_NAME') !== false) {
                        preg_match_all(self::PREG_GET_MODULE_NAME, $line, $matchs);
                        if (isset($matchs[2]) && isset($matchs[2][0])) {
                            return static::$moduleName = trim($matchs[2][0], "'");
                        }
                    }
                }
                fclose($handle);
            }
            break;
        }

        return static::$moduleName = '';
    }

    public static function getFramework()
    {
        if (null !== self::$framework) {
            return self::$framework;
        }

        $index = Container::make('workingDir') . '/index.php';
        if (!file_exists($index) || filesize($index) <= 0) {
            throw new Exception("<error>Can not find index.php from current dir.</error>");
        }

        $contents = file_get_contents($index);
        if (strpos($contents, 'NUWA_START')) {
            self::$framework = self::NUWA;
        } else {
            self::$framework = self::CI;
        }

        return self::$framework;
    }

    public static function isNuwaFramework()
    {
        return self::getFramework() === self::NUWA;
    }

    public static function isCIFramework()
    {
        return self::getFramework() === self::CI;
    }

    public static function getRecordHost()
    {
        $host = self::getMidiConfig()->get('record-host');
        if (!empty($host)) {
            return $host;
        }

        $name = self::getModuleName();
        if (!empty($name) && isset(Module::MODULE[$name])) {
            $config = Module::MODULE[$name];
            if (!empty($config['record-host'])) {
                return $config['record-host'];
            }
        }

        return null;
    }

    public static function getModuleDisfName($moduleName = null)
    {
        // local config have high priority
        $name = self::getMidiConfig()->get('php', 'module-disf-name');
        if (!empty($name)) {
            return $name;
        }

        if (!self::isExistModuleConfig($moduleName)) {
            return '';
        }

        $config = self::getModuleConfig($moduleName);
        return $config['disf'] ?? '';
    }

    public static function getRecommendDSLUrl($moduleName = null)
    {
        if ($moduleName == null) {
            $moduleConfig = self::getModuleConfig();
            $moduleName = $moduleConfig['name'];
        }

        static $url;
        if ($url === null) {
            $url = self::getMidiConfig()->get('php', 'recommend-dsl-url');
        }
        return $url . $moduleName;
    }
}
