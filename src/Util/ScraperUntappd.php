<?php
namespace App\Util;


use Symfony\Component\DomCrawler\Crawler;
use App\Entity\Brewery;
use App\Entity\Beer;
use App\Entity\Style;
use App\Util\Scraper;

class ScraperUntappd
{
    public function __construct($logPath = null, $stashPath = null)
    {
        $this->scraper = new Scraper($logPath, $stashPath);
    }

    public function getCountries()
    {
        $url = 'https://untappd.com/brewery/top_rated?country_id=130';
        if (!$html = $this->scraper->request('country', $url)) {
            return false;
        }

        $crawler = new Crawler($html);

        $countryIds = $crawler->filter('#sort_picker option')->each(function (Crawler $node, $i) {
            $value = $node->attr('value');
            unset($node);

            return $value;
        });

        return $countryIds;
    }


    public function cleanAlcohol($string)
    {
        $string = trim($string);
        $string = preg_replace('/% ABV/', '', $string);

        return $string;
    }

    public function cleanDescription($string)
    {
        $string = trim($string);
        $string = preg_replace('/ Show Less$/', '', $string);

        return $string;
    }

    public function cleanImg($string)
    {
        $md5ProducerUntapp = 'c7e3edf9731c15aee020a804bace74d5';
        $md5BeerUntapp = '3a0176a67c6330ee5d9333a2faa70962';

        if (md5_file($string) == $md5BeerUntapp) {
            return null;
        }

        if (md5_file($string) == $md5ProducerUntapp) {
            return null;
        }

        if (!preg_match('/(\.[a-zA-Z]+$)/', $string, $res)) {
            return null;
        }

        return [
            'file' => $string,
            'name' => md5($string.$string),
            'ext' => $res[0]
        ];
    }

    public function cleanIbu($string)
    {
        $string = trim($string);
        if (preg_match('/([0-9]+)/', $string, $res)) {
            $ibu = $res[0];
        } else {
            $ibu = null;
        }

        return $ibu;
    }

    public function getBeer($beer)
    {
       $url = 'https://untappd.com'.$beer;
       if (!$html = $this->scraper->request($beer, $url)) {
            return false;
        }
        
        try {
            $crawler = new Crawler($html);
            $beerArr = [
                'image' => $this->cleanImg($crawler->filter('.b_info img')->attr('src')),
                'name' => $crawler->filter('.b_info h1')->text(),
                'style' => $crawler->filter('.b_info .style')->text(),
                'abv' => $this->cleanAlcohol($crawler->filter('.b_info .abv')->text()),
                'description' => $this->cleanDescription($crawler->filter('.b_info .beer-descrption-read-less')->text()),
                'ibu' => $this->cleanIbu($crawler->filter('.b_info .ibu')->text()),
            ];

        } catch(\Exception $e) {
            return false;
        }

        return $beerArr;
    }

    public function getBeers($brewery)
    {
       $url = 'https://untappd.com'.$brewery;
        if (!$html = $this->scraper->request($brewery, $url)) {
            return false;
        }
        $crawler = new Crawler($html);
        $beers = $crawler->filter('.sidebar .box:nth-child(3) .content > a')->each(function (Crawler $node, $i) {
            return $node->attr('href'); 
        });

        return $beers;
    }

    public function getBrewery($brewery)
    {
        $url = 'https://untappd.com'.$brewery;
        if (!$html = $this->scraper->request($brewery, $url)) {
            return false;
        }
        $crawler = new Crawler($html);
        try {
            $breweryInfo = [
                'name' => trim($crawler->filter('.top h1')->text()),
                'address' => trim($crawler->filter('.top .brewery')->text()),
                'image' => $this->cleanImg($crawler->filter('.top .label img')->attr('src'))
            ];
        } catch(\Exception $e) {
            return false;
        }

        return $breweryInfo;
    }

    public function getBreweries($country)
    {
        $url = 'https://untappd.com/brewery/top_rated?country_id='.$country;
        if (!$html = $this->scraper->request($country, $url)) {
            return false;
        }

        $crawler = new Crawler($html);

        $breweries = $crawler->filter('.beer-details a')->each(function (Crawler $node, $i) {
            return $node->attr('href');
        });

        return $breweries;
    }

}
