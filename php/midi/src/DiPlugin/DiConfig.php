<?php

/**
 * @author tanmingliang
 */

namespace DiPlugin;

use Midi\Config;
use Midi\Container;
use Midi\Exception\Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * DiDi Plugin Config.
 *
 * Store module's config and information, which will used by DiPlugin to auto generate some context for Midi.
 * So user will no need to config their project at config.yml.
 */
final class DiConfig
{
    const MODULE_CACHE_TIME = 43200;
    const SYNC_TIMEOUT = 2;

    /**
     * Module Information
     *
     * value will be lazy init by internal and update by cloud
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
     * @param array $module
     */
    public static function updateModule(array $module)
    {
        foreach ($module as $name => $configs) {
            if (isset(self::$module[$name])) {
                foreach ($configs as $key => $value) {
                    if ($key === 'uri') {
                        if (!is_array($value)) {
                            $value = [$value,];
                        }
                        self::$module[$name][$key] = array_unique(self::$module[$name][$key], $value);
                    } else {
                        self::$module[$name][$key] = $value;
                    }
                }
            } else {
                self::$module[$name] = $configs;
            }
        }
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

    /**
     * Sync Module Config from `sync-module-url`
     */
    public static function dailySyncModuleConfig()
    {
        $config = self::getMidiConfig();
        $syncUrl = $config->get('php', 'sync-module-url');
        if (empty($syncUrl)) {
            return false;
        }

        $module = Container::make('dependsDir') . DR . 'module.php';
        if (file_exists($module) && (time() - filemtime($module)) < self::MODULE_CACHE_TIME) {
            $config = include $module;
            if (!empty($config)) {
                self::updateModule($config);
                return true;
            }
            return false;
        }

        /** @var OutputInterface $output */
        $output = Container::make('output');
        $client = new Client();
        $res = $client->request('GET', $syncUrl, ['timeout' => self::SYNC_TIMEOUT,]);
        if ($res->getStatusCode() !== 200) {
            $output->writeln("Sync module information from $syncUrl fail!", OutputInterface::VERBOSITY_VERBOSE);
            return false;
        }
        try {
            $resp = \GuzzleHttp\json_decode($res->getBody(), true);
        } catch (\exception $e) {
            $output->writeln("Sync module information from $syncUrl, response invalid json!", OutputInterface::VERBOSITY_VERBOSE);
            return false;
        }

        /**
         * {
         *     'data': [
         *         {"name": "", 'data': "[{key:..., value:...}, {key:..., value:...,}]"},
         *     ],
         *     'errmsg': 'success',
         *     'errno': 0,
         *     'total': 10,
         * }
         */
        if (!isset($resp['errno']) || $resp['errno'] !== 0) {
            $output->writeln("Sync module information from $syncUrl fail, error message: {$resp['errmsg']}!", OutputInterface::VERBOSITY_VERBOSE);
            return false;
        }

        $syncConfig = [];
        if ($resp['total'] > 0) {
            foreach ($resp['data'] as $row) {
                $moduleName = $row['name'];
                try {
                    $configs = \GuzzleHttp\json_decode($row['data'], true);
                } catch (\exception $e) {
                    continue;
                }
                $moduleConfig = [];
                foreach ($configs as $config) {
                    $key = $config['key'];
                    $value = $config['value'];
                    if ($key === 'language' && $value !== 'php') {
                        continue 2;
                    }
                    switch ($key) {
                        case "deploy":
                        case "framework":
                            $moduleConfig[$key] = $value;
                            break;
                        case "context":
                            $moduleConfig['record-host'] = $value;
                            break;
                    }
                }
                if (count($moduleConfig)) {
                    $moduleConfig['name'] = $moduleName;
                    $syncConfig[$moduleName]  = $moduleConfig;
                }
            }
            if (count($syncConfig)) {
                self::updateModule($syncConfig);
                file_put_contents($module, '<?php return '.var_export($syncConfig, true ).";\n");
                return true;
            }
        }
        return false;
    }
}
