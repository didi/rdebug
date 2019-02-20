<?php

namespace DiPlugin\Uploader;

class SearchMate extends BaseMate
{
    public static $count = 0;
    protected static $set = false;

    public static function getActions()
    {
        if (!static::isSet()) {
            return [];
        }

        $mate = self::$mark;
        $mate['id'] = $mate['pid'];
        $mate['count'] = self::$count;
        return [$mate,];
    }
}
