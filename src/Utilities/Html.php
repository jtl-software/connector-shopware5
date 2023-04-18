<?php

namespace jtl\Connector\Shopware\Utilities;

/**
 * Class Description
 * @package jtl\Connector\Shopware\Utilities
 */
class Html
{
    /**
     * @param $string
     * @param $shopUrl
     * @return string
     */
    public static function replacePathsWithFullUrl($string, $shopUrl)
    {
        if (\filter_var($shopUrl, \FILTER_VALIDATE_URL) === false) {
            return $string;
        }

        $hasImages = \preg_match_all('/<img(.*)src=[\'|"]{([^\"]*)}[\'|"]/', $string, $matches);

        if ($hasImages !== false && isset($matches[2])) {
            foreach ($matches[2] as $match) {
                $hasPaths = \preg_match('/path=\'(.*)\'/', $match, $path);
                if ($hasPaths !== false && isset($path[1])) {
                    $search  = "{" . $match . "}";
                    $replace = \sprintf("%s%s", $shopUrl, $path[1]);
                    $string  = \str_replace($search, $replace, $string);
                }
            }
        }
        return $string;
    }
}
