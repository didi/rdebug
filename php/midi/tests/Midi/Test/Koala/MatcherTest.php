<?php

namespace Midi\Test\Koala;

use Midi\Koala\Matcher;
use PHPUnit\Framework\TestCase;

class MatcherTest extends TestCase
{
    public function testEmptyArraySimilarity()
    {
        $this->assertSame(Matcher::cosineSimilarity([], []), 0);
    }

    public function test50Similarity()
    {
        $a = [1 => 1, 2 => 1, 3 => 1, 4 => 1,];
        $b = [1 => 1, 3 => 1, 5 => 1, 7 => 1,];
        $this->assertSame(Matcher::cosineSimilarity($a, $b), 0.5);
    }

    public function test100Similarity()
    {
        $a = [1 => 1, 2 => 1, 3 => 1, 4 => 1,];
        $b = [1 => 1, 2 => 1, 3 => 1, 4 => 1,];
        $this->assertSame(Matcher::cosineSimilarity($a, $b), 1.0);
    }
}
