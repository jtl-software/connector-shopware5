<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

class Translation extends \Shopware_Components_Translation
{
    /**
     * Deletes translation data to the storage.
     *
     * @param  string $type
     * @param  int $key
     * @param  string $language
     * @return Zend_Db_Statement_Pdo
     * @throws Zend_Db_Adapter_Exception To re-throw PDOException.
     */
    public function delete($type, $key, $language = 'all')
    {
        $sql = 'DELETE FROM s_core_translations
                WHERE objecttype = ?
                    AND objectkey = ?';

        $arr = array($type, $key);

        if ($language != 'all') {
            $sql .= ' AND objectlanguage = ?';
            $arr[] = $language;
        }

        return Shopware()->Db()->query($sql, $arr);
    }
}
