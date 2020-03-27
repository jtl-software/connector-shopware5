<?php

namespace jtl\Connector\Shopware\Tests\Utilities;

use jtl\Connector\Shopware\Utilities\Description;
use PHPUnit\Framework\TestCase;

/**
 * Class DescriptionTest
 * @package jtl\Connector\Shopware\Utilities
 */
class DescriptionTest extends TestCase
{
    /**
     * @dataProvider descriptionDataProvider
     * @param $inputString
     * @param $shopUrl
     * @param $expected
     */
    public function testReplacePathsWithFullUrl($inputString, $shopUrl, $expected)
    {
        $output = Description::replacePathsWithFullUrl($inputString, $shopUrl);
        $this->assertEquals($expected, $output);
    }

    /**
     * @return array
     */
    public function descriptionDataProvider()
    {
        return [
            [
                '<img id="111" src="{media path=\'media/image/Teaser-Banner.jpg\'}" srcThis="" thisSrc="" sRc="Bar" /> foo bar',
                "http://foobar.test/",
                '<img id="111" src="http://foobar.test/media/image/Teaser-Banner.jpg" srcThis="" thisSrc="" sRc="Bar" /> foo bar',
            ],
            [
                '<div id="111" src="{media path=\'media/image/Teaser-Banner.jpg\'}"',
                "http://foobar.test/",
                '<div id="111" src="{media path=\'media/image/Teaser-Banner.jpg\'}"',
            ],
            [
                '<img src="{media path=\'media/image/Teaser-Banner.jpg\'}" srcThis="" thisSrc="" sRc="Bar" />
                 <img src="{media path=\'media/image/Teaser-Banner.jpg\'}" srcThis="" thisSrc="" sRc="Bar" />',
                "http://foo.test/",
                '<img src="http://foo.test/media/image/Teaser-Banner.jpg" srcThis="" thisSrc="" sRc="Bar" />
                 <img src="http://foo.test/media/image/Teaser-Banner.jpg" srcThis="" thisSrc="" sRc="Bar" />',
            ],
            [
                "<img src='{media path='media/image/Teaser-Banner.jpg'}' srcThis=\"\" thisSrc=\"\" sRc='{Bar}' /> foo bar",
                "http://foobar.test/",
                '<img src=\'http://foobar.test/media/image/Teaser-Banner.jpg\' srcThis="" thisSrc="" sRc=\'{Bar}\' /> foo bar',
            ]
        ];
    }
}
