<?php
namespace Tests\Util;

use PHPUnit\Framework\TestCase;
use Scraper\Util\Spintax;

class SpintaxTest extends TestCase
{
    public function testSpin()
    {
        $spintax = new Spintax();

        $str = $spintax->process('{a|b}');
        $this->assertTrue(in_array($str, ['a', 'b']));
        
        $str = $spintax->process('{a|b} o {c|d}');
        $this->assertEquals(strlen($str), 5);
        
        $str = $spintax->process('{{a|b}|c}');
        $this->assertTrue(in_array($str, ['a', 'b', 'c']));
        $this->assertEquals(strlen($str), 1);

        for($i=0;$i<10;$i++) {
            $str = $spintax->process('{Cual es|Donde esta} la mejor {cerveza|birra|caldo|vino} del {mundo|universo}');
            //echo $str."\n";
        }
    }

    public function testReplacer()
    {
        $spintax = new Spintax();
        $str = $spintax->replacer("%name%", ['name' => 'a good name']);
        $this->assertEquals($str, 'a good name');

        $str = $spintax->replacer("%name% and %other%", ['name' => 'a good name', 'other' => 'second name']);
        $this->assertEquals($str, 'a good name and second name');
    }
}