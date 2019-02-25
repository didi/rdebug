<?php

/**
 * @author tanmingliang
 */

namespace DiPlugin;

use Midi\Config;
use Midi\Container;
use Midi\Exception\Exception;
use Symfony\Component\Finder\Finder;

/**
 * DiDi Plugin Config.
 *
 * Store module's config and information, which will used by DiPlugin to auto generate some context for Midi.
 * So user will no need to config their project at config.yml.
 */
final class DiConfig
{

    /**
     * Module Information
     *
     * value will be lazy init by internal
     */
    protected static $module = [
//        'xxx-module-name' => [
//            'name'        => 'xxx-module-name',
//            'disf'        => 'disf!xxx',
//            'deploy'      => '/path/to/your/deploy/dir',
//            'log'         => '/path/to/your/log/dir',
//            'record-host' => 'traffic-recorded-machine-name',
//            'uri'         => ['/uri',],
//        ],
    ];

    /**
     * Some dependents code deploy path
     */
    const DEPLOY_SYSTEM_PATH = '/home/xiaoju/webroot/gulfstream/application/system';
    const DEPLOY_BIZ_CONFIG_PATH = '/home/xiaoju/webroot/gulfstream/application/biz-config';

    /**
     * Framework types
     */
    const CI = 'ci';
    const NUWA = 'nuwa';

    /**
     * Regex for get module name
     *
     * which is defined in code, looks like `define('MODULE_NAME', "xxx");`
     */
    const PREG_GET_MODULE_NAME = '/\\bdefine\\(\\s*("(?:[^"\\\\]+|\\\\(?:\\\\\\\\)*.)*"|\'(?:[^\'\\\\]+|\\\\(?:\\\\\\\\)*.)*\')\\s*,\\s*("(?:[^"\\\\]+|\\\\(?:\\\\\\\\)*.)*"|\'(?:[^\'\\\\]+|\\\\(?:\\\\\\\\)*.)*\')\\s*\\);/is';

    private static $moduleName;
    private static $framework;

    /**
     * @return Config
     */
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

        if (!empty(self::$module[$moduleName])) {
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

        if (!isset(self::$module[$module])) {
            throw new Exception("<error>Sorry, not support module $module now.</error>");
        }

        return $moduleConfig = self::$module[$module];
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
                // for nuwa framework
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

    /**
     * Get current module framework type
     *
     * @return string
     * @throws Exception
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
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

    /**
     * Is Nuwa framework (didi internal framework)
     * @return bool
     * @throws Exception
     */
    public static function isNuwaFramework()
    {
        return self::getFramework() === self::NUWA;
    }

    /**
     * Is CodeIgniter framework
     *
     * @return bool
     * @throws Exception
     */
    public static function isCIFramework()
    {
        return self::getFramework() === self::CI;
    }

    /**
     * Return the hostname of recorded session
     *
     * @return |null
     */
    public static function getRecordHost()
    {
        $host = self::getMidiConfig()->get('record-host');
        if (!empty($host)) {
            return $host;
        }

        $name = self::getModuleName();
        if (!empty($name) && isset(self::$module[$name])) {
            $config = self::$module[$name];
            if (!empty($config['record-host'])) {
                return $config['record-host'];
            }
        }

        return null;
    }

    /**
     * Return disf name of module
     *
     * @param string $moduleName
     * @return mixed|string
     * @throws Exception
     */
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

    /**
     * Return the url of get module's recommend dsl
     *
     * @param string $moduleName
     * @return string
     * @throws Exception
     */
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

    /**
     * @return array
     */
    public static function getModule()
    {
        return self::$module;
    }

    /**
     * @param array $module
     */
    public static function setModule(array $module)
    {
        self::$module = $module;
    }

    /**
     * copy \DiPlugin\DiConfig to \Midi\Config
     *
     * @param Config $config
     * @return Config
     */
    public static function copy2MidiConfig(Config $config)
    {
        if (!self::isExistModuleConfig()) {
            return $config;
        }

        $phpConfig = [];
        $module = self::getModuleConfig();
        if (empty($config->get('php', 'deploy-path')) && !empty($module['deploy'])) {
            $phpConfig['deploy-path'] = $module['deploy'];
        }
        if (empty($config->get('php', 'module-name')) && !empty($module['name'])) {
            $phpConfig['module-name'] = $module['name'];
        }
        if (empty($config->get('php', 'module-disf-name')) && !empty($module['disf'])) {
            $phpConfig['module-disf-name'] = $module['disf'];
        }
        $config->merge([
            'php' => $phpConfig,
        ]);

        return $config;
    }
}
