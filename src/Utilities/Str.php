<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

class Str
{
    /**
     * The cache of snake-cased words.
     *
     * @var array
     */
    protected static $snakeCache = [];
/**
     * The cache of camel-cased words.
     *
     * @var array
     */
    protected static $camelCache = [];
/**
     * The cache of studly-cased words.
     *
     * @var array
     */
    protected static $studlyCache = [];
/**
     * Convert a value to camel case.
     *
     * @param  string  $value
     * @return string
     */
    public static function camel($value)
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        $parts       = \explode('_', $value);
        $lastEmpty   = false;
        $toCamelcase = '';
        foreach ($parts as $part) {
            if (empty($part)) {
                $toCamelcase .= '_';
                $lastEmpty    = true;
            } elseif (\is_numeric($part[0])) {
                $toCamelcase .= '_' . $part;
                $lastEmpty    = false;
            } else {
                $toCamelcase .= $lastEmpty ? $part : \ucfirst($part);
                $lastEmpty    = false;
            }
        }
        return static::$camelCache[$value] = \lcfirst($toCamelcase);
    }

    /**
     * Convert a string to snake case.
     *
     * @param  string  $value
     * @param  string  $delimiter
     * @return string
     */
    public static function snake($value, $delimiter = '_')
    {
        $key = $value;
        if (isset(static::$snakeCache[$key][$delimiter])) {
            return static::$snakeCache[$key][$delimiter];
        }

        if (! \ctype_lower($value)) {
            $value = \preg_replace('/\s+/u', '', $value);
            $value = static::lower(\preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return static::$snakeCache[$key][$delimiter] = $value;
    }

    /**
     * Convert a value to studly caps case.
     *
     * @param  string  $value
     * @return string
     */
    public static function studly($value)
    {
        $key = $value;
        if (isset(static::$studlyCache[$key])) {
            return static::$studlyCache[$key];
        }

        $value                            = \ucwords(\str_replace(['-', '_'], ' ', $value));
        return static::$studlyCache[$key] = \str_replace(' ', '', $value);
    }

    /**
     * Convert the given string to lower-case.
     *
     * @param  string  $value
     * @return string
     */
    public static function lower($value)
    {
        return \mb_strtolower($value, 'UTF-8');
    }

    /**
     * Convert the given string to upper-case.
     *
     * @param  string  $value
     * @return string
     */
    public static function upper($value)
    {
        return \mb_strtoupper($value, 'UTF-8');
    }
}
