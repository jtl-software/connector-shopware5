<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2018 JTL-Software GmbH
 *
 * Created at 15.11.2018 10:57
 */
namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Core\Utilities\Language;

class I18n
{
    /**
     * @param string $locale
     * @param object[] $i18ns
     * @return object|null
     * @throws \jtl\Connector\Core\Exception\LanguageException
     */
    public static function find($locale, array $i18ns)
    {
        if(count($i18ns) === 0) {
            return null;
        }

        $languageIso = Language::map($locale);

        $isoGetter = 'getLanguageISO';

        $fallbackI18n = reset($i18ns);
        foreach ($i18ns as $i18n) {
            if(!method_exists($i18n, $isoGetter)) {
                throw new \RuntimeException('Method getLanguageISO does not exist!');
            }

            if($i18n->{$isoGetter}() === $languageIso) {
                return $i18n;
            }
        }

        return $fallbackI18n;
    }
}