<?php

namespace Midi\Parser;

class ParseHTTP implements ParseInterface
{

    public static function match($request, $response = null)
    {
        return preg_match('/^(GET|POST|PUT|DELETE|HEAD|OPTIONS|TRACE|CONNECT).*HTTP\/1\.[1|0]/', $request)
            || preg_match('/^HTTP\/1.[0|1][[:space:]][1-5]\d\d/', $response);
    }

    public static function parse($request, $response = null)
    {
        $lineIdx = strpos($request, "\n");
        $requestLine = substr($request, 0, $lineIdx);
        list($method, $url, $version) = explode(' ', $requestLine);

        $idx = strpos($url, '?');
        if ($idx !== false) {
            $uri = substr($url, 0, $idx);
        } else {
            $uri = $url;
        }

        $continue100 = 0;
        if (strpos($request, '100-continue', $lineIdx) != false) {
            $continue100 = 1;
        }

        $aReq = explode("\r\n\r\n", $request);

        return [
            'method'      => $method,
            'url'         => $url,
            'uri'         => $uri,
            'version'     => trim($version),
            'continue100' => $continue100,
            'body'        => $aReq[1] ?? '',
        ];
    }

    public static function parseResp($response)
    {
        $lineIdx = strpos($response, "\n");
        $responseLine = substr($response, 0, $lineIdx);
        list($version, $httpCode, $httpDesc) = explode(' ', $responseLine);

        $aResp = explode("\r\n\r\n", $response);
        $aHeader = explode("\r\n", $aResp[0]);
        $sBody = $aResp[1];

        return ['version' => $version, 'httpCode' => intval($httpCode), 'header' => $aHeader, 'body' => trim($sBody),];
    }
}
