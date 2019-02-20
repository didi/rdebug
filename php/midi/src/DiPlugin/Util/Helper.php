<?php

/**
 * @author tanmingliang
 * @author fangjunda
 */

namespace DiPlugin\Util;

use Midi\Container;
use DiPlugin\DiConfig;
use Symfony\Component\Finder\Finder;

class Helper
{

    public static $user = null;
    protected static $guid;
    const DEFAULT_USER = 'default';

    public static function generateAllUri()
    {
        $uriHub = [];

        $uriPrefix = DiConfig::getModuleConfig()['uri'];
        $files = new Finder();
        $relativePath = (DiConfig::isNuwaFramework()) ? '/app/controllers/' : '/controllers/';
        $files->files()
            ->in(Container::make('workingDir') . $relativePath)
            ->name('*.php')
            ->exclude('Test')
            ->exclude('test')
            ->notName('welcome.php');
        foreach ($files as $file) {
            $file = substr($file->getRelativePathname(), 0, -4);
            foreach ($uriPrefix as $prefix) {
                $uriHub[] = "$prefix/$file";
            }
        }

        return $uriHub;
    }

    public static function existFastDev2Dir()
    {
        if (is_dir('/Users/didi/xiaoju/webroot/gulfstream/application')) {
            return true;
        }
        return false;
    }

    /**
     * Upload username
     *
     * If you do not want to upload, just disbale in config by `enable-uploader: false`
     */
    public static function user()
    {
        if (null !== self::$user) {
            return self::$user;
        }

        $authFile = $_SERVER['HOME'] . '/.composer/auth.json';
        if (file_exists($authFile)) {
            $auth = json_decode(file_get_contents($authFile), true);
            if (isset($auth['http-basic']) && is_array($auth['http-basic']) && count($auth['http-basic'])) {
                foreach ($auth['http-basic'] as $host => $userInfo) {
                    if (!empty($userInfo['username'])) {
                        return self::$user = $userInfo['username'];
                    }
                }
            }
        }

        $gitConfigFile = $_SERVER['HOME'] . '/.gitconfig';
        if (file_exists($gitConfigFile)) {
            $gitConfig = file_get_contents($gitConfigFile);
            preg_match('/email\s?=\s?(\w+)@/', $gitConfig, $matches);
            if (isset($matches[1])) {
                self::$user = $matches[1];
                return self::$user;
            }
        }

        $sshFile = $_SERVER['HOME'] . '/.ssh/id_rsa.pub';
        if (file_exists($sshFile)) {
            self::$user = md5(trim(file_get_contents($sshFile)));
            return self::$user;
        }

        self::$user = self::DEFAULT_USER;
        return self::$user;
    }

    /**
     * 生成全局唯一 id
     *
     * @param string $salt
     * @param string $algo
     *
     * @return string
     */
    public static function guid($salt = '', $algo = 'md5')
    {
        $data = implode('', [
            uniqid($salt, true),
            self::$guid,
            self::user(),
            $_SERVER['USER'],
            $_SERVER['SCRIPT_FILENAME'],
        ]);
        $hash = strtoupper(hash($algo, $data));

        self::$guid = $hash;
        return self::$guid;
    }

    /**
     * 格式化使用的命令行参数
     *
     * @param $options
     *
     * @return array
     */
    public static function optionFormat($options)
    {
        $result = [];
        foreach ($options as $opt => $value) {
            if (is_bool($value) && $value) {
                $result[$opt] = $value;
            } elseif (is_array($value) && count($value) > 0) {
                $result[$opt] = implode(',', $value);
            } elseif ($value) {
                $result[$opt] = strval($value);
            }
        }

        return json_encode($result);
    }
}
