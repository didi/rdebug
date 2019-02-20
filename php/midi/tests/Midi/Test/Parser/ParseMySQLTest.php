<?php


namespace Midi\Test\Parser;

use PHPUnit\Framework\TestCase;
use Midi\Parser\ParseMySQL;

class ParseMySQLTest extends TestCase
{
    public function testMySQLSet() {
        $request = "\x0f\x00\x00\x00\x03SET NAMES utf8";
        $this->assertSame(ParseMySQL::match($request), ParseMySQL::TYPE_C_S_COM);
    }

    public function testAuthPass() {
        $response = "\x07\x00\x00\x02\x00\x00\x00\x02\x00\x00\x00";
        $this->assertSame(ParseMySQL::isAuthOk($response), true);
    }

    public function testUnpack() {
        $request = "\x07\x00\x00\x00\x02gs_abc";
        $this->assertSame(ParseMySQL::match($request), ParseMySQL::TYPE_C_S_COM);
        $this->assertSame(ParseMySQL::getMessageLen($request), 7);
        $this->assertSame(ParseMySQL::validMessageLen($request), true);
        $this->assertSame(ParseMySQL::getReqMessageType($request), "\x02");
        $this->assertSame(ParseMySQL::toString($request), 'USE gs_abc');

        $request = "\x0f\x00\x00\x00\x03SET NAMES utf8";
        $this->assertSame(ParseMySQL::getMessageLen($request), 15);
        $this->assertSame(ParseMySQL::validMessageLen($request), true);
        $this->assertSame(ParseMySQL::getReqMessageType($request), "\x03");
        $this->assertSame(ParseMySQL::toString($request), "SET NAMES utf8");
    }

    public function testHand() {
        $this->markTestSkipped("need set body");
        // add request to $req
        $req = "";
        $this->assertSame(ParseMySQL::isLoginAuth($req), true);
    }

    public function testisHandInit() {
        $this->markTestSkipped("need set body");
        // add response to $response
        $response = "";
        $this->assertSame(ParseMySQL::isHandShake($response), true);

        $protocol = unpack("C", $response[4]);
        $this->assertSame($protocol[1], 10);

        $response = substr($response, 5);
        $versionPos = strpos($response, "\x00");
        $serverVersionOriginal = substr($response, 0, $versionPos);

        $response = substr($response, $versionPos + 1);
        $threadId = substr($response, 0, 4);

        $authData1 = substr($response, 4, 8);

        $pad = $response[12];
        $this->assertSame($pad, "\x00");

        $response = substr($response, 13);
        $charset = unpack("C", $response[3]);

        $this->assertSame(substr($response, 8, 10), "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00");
    }
}
