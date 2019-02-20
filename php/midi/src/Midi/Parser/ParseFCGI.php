<?php

namespace Midi\Parser;

use Protocol\FCGI;
use Protocol\FCGI\FrameParser;
use Protocol\FCGI\Record\Params;
use Protocol\FCGI\Record\Stdin;

class ParseFCGI
{

    public static function parseRequest($fcgiReq)
    {
        $fcgiParams = [];
        $fcgiStdin = '';
        while (FrameParser::hasFrame($fcgiReq)) {
            $record = FrameParser::parseFrame($fcgiReq);
            if ($record->getType() == FCGI::PARAMS) {
                /* @var $record Params */
                $fcgiParams = array_merge($fcgiParams, $record->getValues());
            } elseif ($record->getType() == FCGI::STDIN) {
                /* @var $record Stdin */
                if ($record->getContentLength() > 0) {
                    $fcgiStdin .= $record->getContentData();
                }
            }
        }

        // request headers & body
        return ['params' => $fcgiParams, 'stdin' => $fcgiStdin,];
    }

    public static function parseResponse($fcgiResp)
    {
        $resp = '';
        while (FrameParser::hasFrame($fcgiResp)) {
            $record = FrameParser::parseFrame($fcgiResp);
            if ($record->getType() == FCGI::STDOUT) {
                $resp .= $record->getContentData();
            }
        }

        return $resp;
    }
}
