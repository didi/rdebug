<?php

namespace DiPlugin\Mock;

/**
 * Generate Disf config.
 *
 * Used by DiDi internal. You could disable by `enable-disf: false` in config.yml
 *
 * @author lixiaodongcifer
 * @author tanmingliang
 * @author fangjunda
 */

use DiPlugin\DiConfig;
use Midi\Config;
use Midi\Console\Application;
use Midi\Container;
use Midi\Exception\Exception;
use Midi\Exception\RuntimeException;
use Midi\Midi;
use Midi\Util\FileUtil;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class MockDisf
{
    public static $excludes = [
        '.git',
        '.svn',
        '__naming__',
        'test',
        'tests',
        'logs',
        'views',
        'vendor/disf/spl',
        'vendor/bin',
        'vendor/platform-ha',
        'vendor/gulfstream',
        'vendor/composer',
    ];

    const PATTERN_PROVIDERS = '@disf![\:\w-]+@';

    /**
     * @param      $sWorkPath
     * @param      $sModule
     * @param bool $increase
     *
     * @return void
     * @throws Exception
     * @throws \Exception
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public static function generate(Config $config, $sWorkPath, $sModule = null, $increase = true)
    {
        // default is enable disf, if config without `enable-disf` $options, will get null
        $enable = $config->get('php', 'enable-disf');
        if ($enable === false || $enable === 0) {
            return;
        }

        list($disf, $increase) = self::getDisf($increase);
        if ($increase && filemtime(Container::make('workingDir')) <= $disf['m_time']) {
            Container::make('output')->writeln('<info>No need to init disf</info>', OutputInterface::VERBOSITY_VERY_VERBOSE);
            return;
        }

        /* $providers is a map */
        $providers = self::scanProviders($sWorkPath, $disf['m_time']);
        $servicesJson = Container::make('DiPluginResDir') . DR . 'disf' . DR . 'services.json';
        $services = json_decode(file_get_contents($servicesJson, 'r'), true);

        $servName = DiConfig::getModuleDisfName($sModule);
        if (empty($servName)) {
            throw new RuntimeException("Can not find current module's disf name!"
                . " You could disable disf by `enable-disf: false` or set module's disf name by `module-disf-name: MODULE_DISF_NAME`"
                . "in config.yml!");
        }

        if ($increase) {
            Container::make('output')->writeln('<info>Upgrade disf in increase way</info>');
            self::upgradeDisfConfig($sWorkPath, $services, $servName, $providers);
        } else {
            Container::make('output')->writeln('<info>Upgrade disf in total amount way</info>');

            /* find consumer cluster id */
            foreach ($services as $s) {
                if ($s['name'] == $servName) {
                    $consumer = $s;
                    self::generateSelfMetaInfo($sWorkPath, $s);
                    break;
                }
            }
            if (empty($consumer)) {
                throw new Exception("<error>Build disf error: cant not find `$servName` in `services.json`.</error>");
            }

            /* create dependent files */
            self::generateDepFiles($sWorkPath, $services, $consumer, $providers);

            /* create __self.json */
            self::generateSelfJson($sWorkPath, $consumer, $providers);

            /* generate .deploy */
            self::createDeploy($sWorkPath, $consumer);
        }

        self::setDisf($disf);
    }

    protected static function scanProviders($dir, $mTime = 0)
    {
        $console_output = Container::make('output');
        $console_output->writeln("<info>Scan <comment>$dir</comment> search Disf depends.</info>");

        $result = [];
        $files = self::getModifiedFiles($dir, $mTime);
        foreach ($files as $file) {
            $console_output->writeln("<info>Scan file: $file</info>", OutputInterface::VERBOSITY_VERY_VERBOSE);

            $handle = fopen($file, "r");
            $comment = false;
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (substr($line, 0, 2) == '//') {
                    continue;
                }
                if (substr($line, 0, 1) == '#') {
                    continue;
                }
                if (substr($line, 0, 2) == '/*' && substr($line, -2, 2) == '*/') {
                    continue;
                }
                if (substr($line, 0, 2) == '/*') {
                    $comment = true;
                    continue;
                }
                if (substr($line, -2, 2) == '*/') {
                    $comment = false;
                    continue;
                }
                if ($comment == true) {
                    continue;
                }
                if (preg_match(self::PATTERN_PROVIDERS, $line, $matches)) {
                    $result[$matches[0]] = $matches[0];
                }
            }
            fclose($handle);
        }

        return $result;
    }

    protected static function upgradeDisfConfig($sWorkPath, $services, $servName, $providers)
    {
        $mateFile = $sWorkPath . '/__naming__/' . $servName . '/__self-metainfo.php';
        $selfFile = $sWorkPath . '/__naming__/__self.json';

        try {
            $newProviders = [];
            include_once($mateFile);
            /** @var array $__disf_config */
            $consumer = $__disf_config['consumers'][0];
            $self = \GuzzleHttp\json_decode(file_get_contents($selfFile), true);
            foreach ($providers as $k => $p) {
                if (in_array($p, $self['providers'])) {
                    continue;
                };

                $newProviders[$k] = $p;
                array_push($self['providers'], $p);
            }
        } catch (\Exception $e) {
            Container::make('output')->writeln("<error>Mistake {$e->getMessage()}in disf config, try -f mode</error>");
            throw new \Exception($e);
        }

        self::generateDepFiles($sWorkPath, $services, $consumer, $newProviders);
        file_put_contents($selfFile, json_encode($self, JSON_PRETTY_PRINT));
        Container::make('output')->writeln('<info>Build: <comment>' . $selfFile . '</comment></info>',
            OutputInterface::VERBOSITY_VERY_VERBOSE);
    }

    protected static function createDeploy($sWorkPath, $consumer)
    {
        $agent_path = $sWorkPath . '/.deploy';
        if (is_link($agent_path) || file_exists($agent_path)) {
            `rm -rf $agent_path`;
        }
        mkdir($agent_path, 0777, true);

        $serv_name = substr($consumer['name'], 5);

        $console_output = Container::make('output');
        $console_output->writeln('<info>Build: <comment>' . $agent_path . '</comment></info>');

        file_put_contents($agent_path . '/service.su.txt', 'offline_docker.' . $serv_name);
        file_put_contents($agent_path . '/service.service_name.txt', $serv_name);
        file_put_contents($agent_path . '/service.cluster.txt', 'offline_docker');

        /* compitable with 1.0 alias! service.json */
        $service = [
            'service_name' => $serv_name,
            'namespace'    => '',
            'cluster'      => 'offline_docker',
        ];

        file_put_contents($agent_path . '/service.json', json_encode($service, JSON_PRETTY_PRINT));

        $namejson = Container::make('DiPluginResDir') . DR . 'disf' . DR . 'naming.json';
        copy($namejson, $sWorkPath . DR . 'naming.json');
    }

    protected static function json2PhpArrayStr($json)
    {
        $jsonStr = json_encode($json, JSON_PRETTY_PRINT);
        $jsonStr = str_replace(':', ' =>', $jsonStr);
        $jsonStr = str_replace('[', ' array(', $jsonStr);
        $jsonStr = str_replace('{', ' array(', $jsonStr);
        $jsonStr = str_replace(']', ' )', $jsonStr);
        $jsonStr = str_replace('}', ' )', $jsonStr);

        return $jsonStr;
    }

    protected static function generateSelfJson($sWorkPath, $consumer, $providers)
    {
        $agent_path = $sWorkPath . '/__naming__';
        if (!file_exists($agent_path)) {
            mkdir($agent_path, 0777, true);
        }

        $selfJson = [
            'providers' => array_values($providers),
            'names'     => [$consumer['name']],
        ];

        file_put_contents($agent_path . '/__self.json', json_encode($selfJson, JSON_PRETTY_PRINT));

        $console_output = Container::make('output');
        $console_output->writeln('<info>Build: <comment>' . $agent_path . '/__self.json</comment></info>');

        return true;
    }

    protected static function generateSelfMetaInfo($sWorkPath, $serv)
    {
        $self_meta_template = <<<'HHHHH'
<?php
$__disf_config = array(
    "consumers" => array(
        {selfinfo}
    )
);
HHHHH;
        $agent_path = $sWorkPath . '/__naming__/' . $serv['name'];
        if (!file_exists($agent_path)) {
            mkdir($agent_path, 0777, true);
        }
        $serv = self::json2PhpArrayStr($serv);
        file_put_contents($agent_path . '/__self-metainfo.php', str_replace('{selfinfo}', $serv, $self_meta_template));

        $output = Container::make('output');
        $output->writeln("<info>Build: <comment>" . $agent_path . '/__self-metainfo.php</comment></info>');

        return true;
    }

    protected static function generateDepFiles($sWorkPath, $servs, $consumer, $deps)
    {
        $template = <<<'HHHHH'
<?php
$__disf_config = array(
  "{consumerId}" => array(
    "{depId}" => {disfinfo}
  )
);
HHHHH;

        $consumerId = $consumer['clusterId'];
        $consumerName = $consumer['name'];
        $agent_path = $sWorkPath . '/__naming__/' . $consumerName . '/__discovery-tables';
        if (!file_exists($agent_path)) {
            mkdir($agent_path, 0777, true);
        }

        /** @var $midi Midi */
        $midi = Container::make('midi');
        $outboundPort = $midi->getKoala()->getOutboundPort();
        $console_output = Container::make('output');
        $console_output->writeln('<info>Build: <comment>' . $agent_path . '</comment></info>');

        foreach ($servs as $s) {
            if (count($deps) == 0) {
                break;
            }
            if (!isset($deps[$s['name']])) {
                continue;
            }
            $depId = $s['clusterId'];
            $depName = $s['name'];
            foreach ($s['endpoints'] as $k => &$vs) {
                foreach ($vs as &$v) {
                    $v['ip'] = '127.0.0.1';
                    $v['port'] = $outboundPort;
                }
            }
            $s = self::json2PhpArrayStr($s);
            $output = str_replace('{consumerId}', $consumerId, $template);
            $output = str_replace('{depId}', $depId, $output);
            $output = str_replace('{disfinfo}', $s, $output);
            file_put_contents($agent_path . '/' . $depName . '.php', $output);
            $console_output->writeln('<info>Build: <comment>' . $agent_path . '/' . $depName . '.php</comment></info>', OutputInterface::VERBOSITY_VERBOSE);

            unset($deps[$depName]);
        }

        if (count($deps) > 0) {
            $console_output->writeln("<info>Build: <comment>$consumerName</comment> depends, miss these services: <error>"
                                     . implode(', ', $deps) . "</error>.</info>"
            );
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param bool $increase
     *
     * @return array
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    protected static function getDisf($increase)
    {
        $disfConfigFile = Container::make('workingDir') . '/.midi/__disf.json';
        FileUtil::createFile($disfConfigFile);

        try {
            $disf = \GuzzleHttp\json_decode(file_get_contents($disfConfigFile), true);
            self::checkDisfFormat($disf);
        } catch (\Exception $e) {
            Container::make('output')->writeln('<info>The format of <comment>.midi/__disf.json</comment> is updated</info>');
            $disf = [
                'v_midi' => Application::getMidiVersion(),
                'm_time' => 0,
                'services_modify_time' => 0,
            ];
        }

        if (version_compare(Application::getMidiVersion(), $disf['v_midi']) > 0 || 0 === $disf['m_time']) {
            Container::make('output')->writeln('<info>The <comment>midi.phar</comment> is updated, so it will rebuild all files any way</info>');
            $increase = false;
        }

        $file = Container::make('DiPluginResDir') . DR . 'disf' . DR . 'services.json';
        $pharServicesModifyTime = filemtime($file);
        if ($disf['services_modify_time'] < $pharServicesModifyTime) {
            Container::make('output')->writeln('<info>The <comment>services.json</comment> is updated, so it will rebuild all files any way</info>');
            $increase = false;
        }

        // 全量更新必须把 m_time 置零
        if (false === $increase) {
            $disf['m_time'] = 0;
            $disf['services_modify_time'] = $pharServicesModifyTime;
        }
        return [$disf, $increase];
    }

    protected static function setDisf($disf)
    {
        self::checkDisfFormat($disf);
        $disf['v_midi'] = Application::getMidiVersion();
        $disf['m_time'] = filemtime(Container::make('workingDir'));

        $disfFile = Container::make('workingDir') . '/.midi/__disf.json';
        file_put_contents($disfFile, json_encode($disf, JSON_PRETTY_PRINT));
        Container::make('output')->writeln('<info>Build: <comment>' . $disfFile . '</comment></info>');
    }

    protected static function checkDisfFormat($disf)
    {
        if (!is_array($disf) || !isset($disf['v_midi']) || !is_string($disf['v_midi']) ||
            !isset($disf['m_time']) || !is_int($disf['m_time']) || $disf['m_time'] < 0 ||
            !isset($disf['services_modify_time']) || !is_int($disf['services_modify_time']) || $disf['services_modify_time'] < 0
        ) {
            throw new \Exception('__disf.json not valid');
        }
    }

    protected static function getModifiedFiles($dir, $mTime)
    {
        $files = [];
        $dirHub = [$dir];
        while (count($dirHub) > 0) {
            $subDir = array_shift($dirHub);
            $finder = new Finder();
            $finder->in($subDir)
                ->filter(function (\SplFileInfo $file) use ($mTime) {
                    return $file->getMTime() > $mTime;
                })
                ->notPath('#vendor/.*/test#')
                ->exclude(self::$excludes)
                ->depth(0)
                ->ignoreUnreadableDirs(true);
            foreach ($finder as $file) {
                $filename = $file->getPathname();
                if ($file->isDir()) {
                    $dirHub[] = $filename;
                }

                if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                    $files[] = $filename;
                }
            }
        }

        return $files;
    }
}
