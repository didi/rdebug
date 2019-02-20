<?php

namespace Midi\Parser;

use Clue\Redis\Protocol\Parser\RequestParser;

class ParseRedis implements ParseInterface
{

    /**
     * @see RecursiveSerializer::getRequestMessage
     */
    public static function match($request, $response = null)
    {
        $crlf = strpos($request, "\r\n");
        if ($crlf === false) {
            return false;
        }
        if ($request[0] !== '*') {
            return false;
        }
        $items = explode("\r\n", $request);
        if (count($items) < 3) {
            return false;
        }
        if ($items[0][0] == '*' && $items[1][0] === '$') {
            return true;
        }
        return false;
    }

    public static function parse($request, $response = null)
    {
        $parser = new RequestParser();
        $commands = [];
        foreach ($parser->pushIncoming($request) as $command) {
            /* @var $command \Clue\Redis\Protocol\Model\Request */
            $commands[] = ['command' => $command->getCommand(), 'args' => $command->getArgs(),];
        }
        return $commands;
    }
}