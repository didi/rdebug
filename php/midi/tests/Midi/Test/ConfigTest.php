<?php

namespace Midi\Test;

use Midi\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testConfigReplaceMerge()
    {
        $config = new Config();
        $this->assertSame($config->get('koala', 'inbound-port'), 5514);
        $config->merge([
            'koala' => [
                'inbound-port' => 6515,
            ],
        ]);
        $this->assertSame($config->get('koala', 'inbound-port'), 6515);
    }

    public function testConfigMerge()
    {
        $config = new Config();
        $this->assertSame($config->get('php', 'preload-plugins'), ['Midi\ElasticPlugin']);

        $config->merge([
            'php' => [
                'preload-plugins' => [
                    'Midi\Plugin',
                ],
            ],
        ]);
        $this->assertSame($config->get('php', 'preload-plugins'), ['Midi\ElasticPlugin', 'Midi\Plugin']);
    }

    public function testMergeUniq()
    {
        $config = new Config();
        $config->merge([
            'php' => [
                'preload-plugins' => [
                    'Midi\Plugin',
                    'Midi\Plugin',
                ],
            ],
        ]);
        $this->assertSame($config->get('php', 'preload-plugins'), ['Midi\ElasticPlugin', 'Midi\Plugin']);
    }
}
