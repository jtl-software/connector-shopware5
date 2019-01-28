<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Checksum;

use \jtl\Connector\Checksum\IChecksumLoader;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use \jtl\Connector\Core\Logger\Logger;

final class ChecksumLoader implements IChecksumLoader
{
    public function read($endpointId, $type)
    {
        if ($endpointId === null || !IdConcatenator::isProductId($endpointId)) {
            return '';
        }

        list ($detailId, $productId) = IdConcatenator::unlink($endpointId);

        $result = Shopware()->Db()->fetchOne(
            'SELECT checksum FROM jtl_connector_product_checksum WHERE product_id = ? AND detail_id = ? AND type = ?',
            array($productId, $detailId, $type)
        );

        Logger::write(sprintf('Read with endpointId (%s), type (%s) - result (%s)', $endpointId, $type, $result), Logger::DEBUG, 'checksum');

        return ($result !== false) ? $result : '';
    }

    public function write($endpointId, $type, $checksum)
    {
        if ($endpointId === null || !IdConcatenator::isProductId($endpointId)) {
            return false;
        }

        list ($detailId, $productId) = IdConcatenator::unlink($endpointId);

        $sql = '
            INSERT IGNORE INTO jtl_connector_product_checksum
            (
                product_id, detail_id, type, checksum
            )
            VALUES (?,?,?,?)
        ';

        Logger::write(sprintf('Write with endpointId (%s), type (%s) - checksum (%s)', $endpointId, $type, $checksum), Logger::DEBUG, 'checksum');

        $statement = Shopware()->Db()->query($sql, array($productId, $detailId, $type, $checksum));

        return $statement ? true : false;
    }

    public function delete($endpointId, $type)
    {
        if ($endpointId === null || !IdConcatenator::isProductId($endpointId)) {
            return false;
        }

        list ($detailId, $productId) = IdConcatenator::unlink($endpointId);

        $rows = Shopware()->Db()->delete('jtl_connector_product_checksum', array('product_id = ?' => $productId, 'detail_id = ?' => $detailId));

        Logger::write(sprintf('Delete with endpointId (%s), type (%s)', $endpointId, $type), Logger::DEBUG, 'checksum');

        return $rows ? true : false;
    }
}