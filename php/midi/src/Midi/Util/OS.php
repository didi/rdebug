<?php
/**
 * @author tanmingliang
 */

namespace Midi\Util;

/**
 * operating system util
 */
class OS
{
    /**
     * Returns true if operating system is based on GNU linux.
     *
     * @return boolean
     */
    public static function isLinux()
    {
        return (bool)stristr(PHP_OS, 'linux');
    }

    /**
     * Returns true if operating system is based on apple MacOS.
     *
     * @return boolean
     */
    public static function isMacOs()
    {
        return stristr(PHP_OS, 'darwin') || stristr(PHP_OS, 'mac');
    }
}