<?php

namespace Midi\Test\Parser;

use PHPUnit\Framework\TestCase;
use Midi\Parser\ParseHTTP;

class ParseHTTPTest extends TestCase
{

    public function testHTTP() {
        $request = <<<EOF
POST /service/wsgsigCheck HTTP/1.1
Host: 127.0.0.1:5516
Accept: */*
Content-Length: 2785
Content-Type: application/x-www-form-urlencoded
Expect: 100-continue

EOF;

        $this->assertEquals(ParseHTTP::match($request), true);
        $this->assertEquals(ParseHTTP::parse($request),
            [
                'method'      => "POST",
                'url'         => "/service/wsgsigCheck",
                'uri'         => "/service/wsgsigCheck",
                'version'     => "HTTP/1.1",
                'continue100' => 1,
                'body'        => '',
            ]
        );
    }

    public function testResponse() {
        $response = <<<EOF
HTTP/1.1 100 Continue

EOF;
        $this->assertSame(ParseHTTP::parseResp($response),
            [
                'version'  => 'HTTP/1.1',
                'httpCode' => 100,
                'header' => [
                    "HTTP/1.1 100 Continue\n"
                ],
                'body' => '',
            ]
        );
    }
}
