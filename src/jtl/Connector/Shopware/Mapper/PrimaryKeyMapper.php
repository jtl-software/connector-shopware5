<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Mapper\IPrimaryKeyMapper;
use \jtl\Connector\Linker\IdentityLinker;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use \jtl\Connector\Shopware\Model\Image;

class PrimaryKeyMapper implements IPrimaryKeyMapper
{
    public function getHostId($endpointId, $type)
    {
        $dbInfo = $this->getTableInfo($type);
        switch ($type) {
            case IdentityLinker::TYPE_PRODUCT:
                list ($detailId, $productId) = IdConcatenator::unlink($endpointId);

                $hostId = Shopware()->Db()->fetchOne(
                    'SELECT host_id FROM ' . $dbInfo['table'] . ' WHERE ' . $dbInfo['pk'] . ' = ? AND detail_id = ?',
                    array($productId, $detailId)
                );
                break;            
            case IdentityLinker::TYPE_IMAGE:
                list ($mediaType, $foreignId, $mediaId) = IdConcatenator::unlink($endpointId);

                $hostId = Shopware()->Db()->fetchOne(
                    'SELECT host_id FROM jtl_connector_link_product_image WHERE id = ?',
                    array($foreignId)
                );

                if ($hostId === false) {
                    $hostId = Shopware()->Db()->fetchOne(
                        'SELECT host_id FROM ' . $dbInfo['table'] . ' WHERE ' . $dbInfo['pk'] . ' = ?',
                        array($mediaId)
                    );
                }
                break;
            default:
                $hostId = Shopware()->Db()->fetchOne(
                    'SELECT host_id FROM ' . $dbInfo['table'] . ' WHERE ' . $dbInfo['pk'] . ' = ?',
                    array($endpointId)
                );
                break;
        }

        Logger::write(sprintf('Trying to get hostId with endpointId (%s) and type (%s) ... hostId: (%s)', $endpointId, $type, $hostId), Logger::DEBUG, 'linker');

        return ($hostId !== false) ? (int)$hostId : null;
    }

    public function getEndpointId($hostId, $type)
    {
        $dbInfo = $this->getTableInfo($type);
        $endpointId = false;
        switch ($type) {
            case IdentityLinker::TYPE_PRODUCT:
                $res = Shopware()->Db()->fetchAll(
                    'SELECT ' . $dbInfo['pk'] . ', detail_id FROM ' . $dbInfo['table'] . ' WHERE host_id = ?',
                    array($hostId)
                );

                if (is_array($res) && count($res) > 0) {
                    $endpointId = IdConcatenator::link(array($res[0]['detail_id'], $res[0][$dbInfo['pk']]));
                }
                break;
            case IdentityLinker::TYPE_IMAGE:
                $endpointId = Shopware()->Db()->fetchOne(
                    'SELECT image_id FROM jtl_connector_link_product_image WHERE host_id = ?',
                    array($hostId)
                );

                if ($endpointId === false) {
                    $endpointId = Shopware()->Db()->fetchOne(
                        'SELECT image_id FROM ' . $dbInfo['table'] . ' WHERE host_id = ?',
                        array($hostId)
                    );  
                }
                break;
            default:
                $endpointId = Shopware()->Db()->fetchOne(
                    'SELECT ' . $dbInfo['pk'] . ' FROM ' . $dbInfo['table'] . ' WHERE host_id = ?',
                    array($hostId)
                );
                break;
        }

        Logger::write(sprintf('Trying to get endpointId with hostId (%s) and type (%s) ... endpointId: (%s)', $hostId, $type, $endpointId), Logger::DEBUG, 'linker');

        return ($endpointId !== false) ? $endpointId : null;
    }

    public function save($endpointId, $hostId, $type)
    {
        Logger::write(sprintf('Save link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type), Logger::DEBUG, 'linker');

        $dbInfo = $this->getTableInfo($type);
        switch ($type) {
            case IdentityLinker::TYPE_PRODUCT:
                list ($detailId, $productId) = IdConcatenator::unlink($endpointId);

                $sql = '
                    INSERT IGNORE INTO ' . $dbInfo['table'] . '
                    (
                        product_id, detail_id, host_id
                    )
                    VALUES (?,?,?)
                ';

                $statement = Shopware()->Db()->query($sql, array($productId, $detailId, $hostId));
                break;
            case IdentityLinker::TYPE_IMAGE:
                list ($mediaType, $foreignId, $mediaId) = IdConcatenator::unlink($endpointId);

                if ($mediaType === Image::MEDIA_TYPE_PRODUCT) {
                    $sql = '
                        INSERT IGNORE INTO jtl_connector_link_product_image
                        (
                            id, host_id, image_id
                        )
                        VALUES (?,?,?)
                    ';

                    $statement = Shopware()->Db()->query($sql, array($foreignId, $hostId, $endpointId));
                } else {
                    $sql = '
                        INSERT IGNORE INTO ' . $dbInfo['table'] . '
                        (
                            image_id, media_id, host_id
                        )
                        VALUES (?,?,?)
                    ';

                    $statement = Shopware()->Db()->query($sql, array($endpointId, $mediaId, $hostId));
                }
                break;
            default:
                $sql = '
                    INSERT IGNORE INTO ' . $dbInfo['table'] . '
                    (
                        ' . $dbInfo['pk'] . ', host_id
                    )
                    VALUES (?,?)
                ';

                $statement = Shopware()->Db()->query($sql, array($endpointId, $hostId));
                break;
        }

        return $statement ? true : false;
    }

    public function delete($endpointId = null, $hostId = null, $type)
    {
        Logger::write(sprintf('Delete link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type), Logger::DEBUG, 'linker');

        $dbInfo = $this->getTableInfo($type);
        if ($endpointId) {
            switch ($type) {
                case IdentityLinker::TYPE_PRODUCT:
                    list ($detailId, $productId) = IdConcatenator::unlink($endpointId);

                    $where = array('product_id = ?' => $productId, 'detail_id = ?' => $detailId);
                    break;                
                case IdentityLinker::TYPE_IMAGE:
                    list ($mediaType, $foreignId, $mediaId) = IdConcatenator::unlink($endpointId);

                    $where = ($mediaType === Image::MEDIA_TYPE_PRODUCT) ? 
                        array('id = ?' => $foreignId) : array($dbInfo['pk'] . ' = ?' => $mediaId);
                    break;
                default:
                    $where = array($dbInfo['pk'] . ' = ?' => $endpointId);
                    break;
            }
        }

        if ($hostId) {
            $where = array('host_id = ?' => $hostId);
        }

        $rows = Shopware()->Db()->delete($dbInfo['table'], $where);

        return $rows ? true : false;
    }

    public function clear()
    {
        Logger::write('Clearing linking tables', Logger::DEBUG, 'linker');

        $statement = Shopware()->Db()->query(
            '
             TRUNCATE TABLE jtl_connector_link_category;
             TRUNCATE TABLE jtl_connector_link_customer;
             TRUNCATE TABLE jtl_connector_link_detail;
             TRUNCATE TABLE jtl_connector_link_image;
             TRUNCATE TABLE jtl_connector_link_product_image;
             TRUNCATE TABLE jtl_connector_link_manufacturer;
             TRUNCATE TABLE jtl_connector_link_note;
             TRUNCATE TABLE jtl_connector_link_order;
             TRUNCATE TABLE jtl_connector_link_product;
             TRUNCATE TABLE jtl_connector_link_specific;
             TRUNCATE TABLE jtl_connector_link_payment;'
        );

        return $statement ? true : false;
    }

    public function gc()
    {
        return true;
    }

    protected function getTableInfo($type)
    {
        switch ($type) {
            case IdentityLinker::TYPE_CATEGORY:
                return array(
                    'table' => 'jtl_connector_link_category', 
                    'pk' => 'category_id'
                );
            case IdentityLinker::TYPE_CUSTOMER:
                return array(
                    'table' => 'jtl_connector_link_customer', 
                    'pk' => 'customer_id'
                );
            case IdentityLinker::TYPE_PRODUCT:
                return array(
                    'table' => 'jtl_connector_link_detail', 
                    'pk' => 'product_id'
                );
            case IdentityLinker::TYPE_IMAGE:
                return array(
                    'table' => 'jtl_connector_link_image', 
                    'pk' => 'media_id'
                );
            case IdentityLinker::TYPE_MANUFACTURER:
                return array(
                    'table' => 'jtl_connector_link_manufacturer', 
                    'pk' => 'manufacturer_id'
                );
            case IdentityLinker::TYPE_DELIVERY_NOTE:
                return array(
                    'table' => 'jtl_connector_link_note', 
                    'pk' => 'note_id'
                );
            case IdentityLinker::TYPE_CUSTOMER_ORDER:
                return array(
                    'table' => 'jtl_connector_link_order', 
                    'pk' => 'order_id'
                );
            case IdentityLinker::TYPE_SPECIFIC:
                return array(
                    'table' => 'jtl_connector_link_specific', 
                    'pk' => 'specific_id'
                );
            case IdentityLinker::TYPE_SPECIFIC_VALUE:
                return array(
                    'table' => 'jtl_connector_link_specific_value',
                    'pk' => 'specific_value_id'
                );
            case IdentityLinker::TYPE_PAYMENT:
                return array(
                    'table' => 'jtl_connector_link_payment',
                    'pk' => 'payment_id'
                );
        }
    }
}
