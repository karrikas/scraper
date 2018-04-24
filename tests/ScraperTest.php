<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use Scraper\Scraper;

class ScraperTest extends TestCase
{
    protected function setUp()
    {
        $this->su = new Scraper(null, null);
    }

    public function testGetUseragentRandom()
    {
        $items = $this->su->getUseragentRandom();
        $this->assertEquals(1, count($items));
    }

    public function testRequest()
    {
    	$html = $this->su->request('test', 'https://github.com/karrikas/scraper');
    	$this->assertTrue(is_string($html));
    }
}
