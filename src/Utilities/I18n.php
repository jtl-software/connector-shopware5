<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2018 JTL-Software GmbH
 *
 * Created at 15.11.2018 10:57
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Core\Exception\LanguageException;
use jtl\Connector\Core\Utilities\Language;

/**
 * Class I18n
 * @package jtl\Connector\Shopware\Utilities
 */
class I18n
{
    /**
     * @param string $locale
     * @param mixed ...$i18ns
     * @return mixed|null
     * @throws LanguageException
     */
    public static function findByLocale(string $locale, ...$i18ns)
    {
        $languageIso = Language::map($locale);
        return self::findByLanguageIso($languageIso, ...$i18ns);
    }

    /**
     * @param $languageIso
     * @param mixed ...$i18ns
     * @return mixed
     * @throws \Exception
     */
    public static function findByLanguageIso(string $languageIso, ...$i18ns)
    {
        if (count($i18ns) > 0) {
            $isoGetter = 'getLanguageISO';

            foreach ($i18ns as $i18n) {
                if (!method_exists($i18n, $isoGetter)) {
                    throw new \RuntimeException('Method getLanguageISO does not exist!');
                }

                if ($i18n->{$isoGetter}() === $languageIso) {
                    return $i18n;
                }
            }

            return reset($i18ns);
        }

        throw new \Exception('No translation found');
    }
}
