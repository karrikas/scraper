<?php
namespace Scraper\Util;

/**
 * Spintax - A helper class to process Spintax strings.
 * @name Spintax
 * @author Jason Davis - https://www.codedevelopr.com/
 * Tutorial: https://www.codedevelopr.com/articles/php-spintax-class/
 */
class Spintax
{
    public function process($text)
    {
        return preg_replace_callback(
            '/\{(((?>[^\{\}]+)|(?R))*)\}/x',
            array($this, 'replace'),
            $text
        );
    }

    private function replace($text)
    {
        $text = $this->process($text[1]);
        $parts = explode('|', $text);
        return $parts[array_rand($parts)];
    }

    public function replacer(string $text, array $params = [])
    {
        foreach ($params as $key => $value) {
           $text = str_replace('%'.$key.'%', $value, $text);
        }

        return $text;
    }
}