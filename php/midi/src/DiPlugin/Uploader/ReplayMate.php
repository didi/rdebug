<?php

namespace DiPlugin\Uploader;

use DiPlugin\Util\Helper;

class ReplayMate extends BaseMate
{
    /*
     * sessions 结构
     * ["1536931208335592495-23331": ["latency": 10, "same": 1], ...]
     */
    public static $sessions = [];
    protected static $set = false;

    public static function getActions()
    {
        if (!static::isSet()) {
            return [];
        }

        if (0 == count(self::$sessions)) {
            $mate = self::$mark;
            $mate['id'] = Helper::guid(self::$mark['command']);
            return [$mate,];
        }

        $result = [];
        foreach (self::$sessions as $session => $status) {
            $mate = self::$mark;
            $mate['id'] = Helper::guid(self::$mark['command']);
            $mate['session'] = $session;
            $mate['latency'] = $status['latency'];
            $mate['same'] = $status['same'];
            $result[] = $mate;
        }

        return $result;
    }
}
