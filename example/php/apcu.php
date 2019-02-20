<?php

/**
 * In order to record apcu operation, you should this SDK instead of apcu_*
 */
class Apcu
{
    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public static function store($key, $value)
    {
        return \apcu_store($key, $value);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public static function delete($key)
    {
        return \apcu_delete($key);
    }

    /**
     * @param string $key
     *
     * @return bool|mixed
     */
    public static function fetch($key)
    {
        $value = \apcu_fetch($key);
        if ($value !== false) {
            self::sendToKoala($key, $value);
        }

        return $value;
    }

    /**
     * Send UDP To Koala Recorder, record apcu value
     *
     * @param string $key
     * @param mixed $value
     */
    private static function sendToKoala($key, $value)
    {
        $koalaEnv = getenv("KOALA_SO");
        if ($koalaEnv) {
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            // udp packet limit 2^16 = 65536
            $sValue = substr(serialize($value), 0, 60000);
            $msg = "to-koala!read-storage\napcu_fetch\n$key\n".$sValue;
            @socket_sendto($socket, $msg, strlen($msg), 0, '127.127.127.127', 127);
            @socket_close($socket);
        }
    }
}
