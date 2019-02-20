<?php

namespace Midi\Test;

use Midi\Container;
use spl_object_hash;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function testIns()
    {
        $c = Container::ins();
        $this->assertInstanceOf(Container::class, $c);
    }

    /**
     * @expectedException \Midi\Exception\ContainerValueNotFoundException
     */
    public function testNotFound()
    {
        Container::make('abc');
    }

    public function testBind()
    {
        Container::bind('abc', 'abc');
        $this->assertSame(Container::make('abc'), 'abc');
    }

    public function testNotHas()
    {
        $this->assertNotTrue(Container::ins()->has('bar'));
    }

    public function testHas()
    {
        Container::bind('foo', 'abc');
        $this->assertTrue(Container::ins()->has('foo'));
    }

    public function testSingleton()
    {
        $obj = new \stdClass();
        Container::bind('abc', $obj);

        $a1 = Container::make('abc');
        $a2 = Container::make('abc');
        $this->assertSame(spl_object_hash($a1), spl_object_hash($a2));
    }
}
