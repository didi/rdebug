<?php
/**
 * @author fanyitian
 */

namespace DiPlugin\Mock;

class FixCI
{
    private static $_done = false;

    public static function fix()
    {
        if (self::$_done) {
            return;
        }
        self::fixPushGulfstream();
        register_shutdown_function([FixCI::class, 'fixPushGulfstream',], true);
        self::$_done = true;
    }

    /**
     * 由于mac环境大小写问题，导致 push require_once 重复加载.
     *
     * @param bool $bRecover
     */
    public static function fixPushGulfstream($bRecover = false)
    {
        $cwd = getcwd();
        $fPushFile = $cwd.'/libraries/pushgulfstream.php';
        if (file_exists($fPushFile)) {
            $sCode = file_get_contents($fPushFile);
            $aReplaces = [
                'require_once (BIZ_LIB_CIPATH . "libraries/didi_push_interface.php");',
            ];
            foreach ($aReplaces as $replace) {
                $sPatch = "// ".$replace;
                if ($bRecover) {
                    $sCode = str_replace($sPatch, $replace, $sCode);
                } else {
                    $sCode = str_replace($replace, $sPatch, $sCode);
                }
            }

            $sOriginLoad = '$CI =& get_instance();';
            $sPatchLoad = '$CI =& get_instance();
            $CI->load->library(\'didi_push_interface\');';
            if ($bRecover) {
                $sCode = str_replace($sPatchLoad, $sOriginLoad, $sCode);
            } else {
                $sCode = str_replace($sOriginLoad, $sPatchLoad, $sCode);
            }

            file_put_contents($fPushFile, $sCode);
        }
    }
}