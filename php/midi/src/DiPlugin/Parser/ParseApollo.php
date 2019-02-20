<?php

namespace DiPlugin\Parser;

use Midi\Parser\ParseInterface;
use Midi\Koala\Replayed\SendUDP;

/**
 * @see \Disf\SPL\ApolloV2\UDPMessenger::toggleMetrics
 */
class ParseApollo implements ParseInterface
{

    const MISSING_TOGGLE = 0;
    const TOGGLE_METRICS = 1;
    const ACCESS_LOG = 2;
    const ERROR_LOG = 3;
    const PUBLIC_LOG = 4;
    const REPORT = 7;
    const MISSING_CONFIG = 8;

    public static function match($action, $response = null)
    {
        if ($action instanceof SendUDP) {
            $sendUDP = $action;
        } else {
            $sendUDP = new SendUDP($action);
        }
        if ($sendUDP->getPeer()->getIP() === '127.0.0.1' && $sendUDP->getPeer()->getPort() === 9891) {
            return true;
        }

        return false;
    }

    public static function parse($request, $response = null)
    {
        $items = explode("\t", $request);
        $count = count($items);

        if ($count == 2) {
            if ($items[0] == self::MISSING_TOGGLE) {
                return ['type' => self::MISSING_TOGGLE, 'toggle' => $items[1]];
            }
            if ($items[0] == self::ACCESS_LOG) {
                return ['type' => self::ACCESS_LOG, 'log' => $items[1]];
            }
            if ($items[0] == self::ERROR_LOG) {
                return ['type' => self::ERROR_LOG, 'log' => $items[1]];
            }
            if ($items[0] == self::PUBLIC_LOG) {
                return ['type' => self::PUBLIC_LOG, 'log' => $items[1]];
            }
        }

        if ($count == 3 && $items[0] == self::MISSING_CONFIG) {
            return ['type' => self::MISSING_CONFIG, 'ns' => $items[1], 'config' => $items[2],];
        }

        if ($count == 5 && $items[0] == self::TOGGLE_METRICS && is_numeric($items[2]) && ($items[2] == 0 || $items[2] == 1)) {
            return ['type' => self::TOGGLE_METRICS, 'toggle' => $items[1], 'allow' => $items[2],];
        }

        return false;
    }
}
