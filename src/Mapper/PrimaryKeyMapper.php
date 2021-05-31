<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Drawing\ImageRelationType;
use jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Mapper\IPrimaryKeyMapper;
use \jtl\Connector\Linker\IdentityLinker;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use \jtl\Connector\Shopware\Model\Image;

class PrimaryKeyMapper implements IPrimaryKeyMapper
{
    public function getHostId($endpointId, $type)
    {
        $hostId = false;
        $dbInfo = $this->getTableInfo($type);
        if ($dbInfo !== null) {
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

                    if ($mediaType === Image::MEDIA_TYPE_PRODUCT) {
                        $hostId = Shopware()->Db()->fetchOne(
                            'SELECT host_id FROM jtl_connector_link_product_image WHERE id = ?',
                            array($foreignId)
                        );
                    } else {
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
        }

        Logger::write(sprintf('Trying to get hostId with endpointId (%s) and type (%s) ... hostId: (%s)', $endpointId, $type, $hostId), Logger::DEBUG, 'linker');

        return ($hostId !== false) ? (int) $hostId : null;
    }

    public function getEndpointId($hostId, $type, $relationType = null)
    {
        $endpointId = false;
        $dbInfo = $this->getTableInfo($type);
        if ($dbInfo !== null) {
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
                    if ($relationType === ImageRelationType::TYPE_PRODUCT) {
                        $endpointId = Shopware()->Db()->fetchOne(
                            'SELECT image_id FROM jtl_connector_link_product_image WHERE host_id = ?',
                            array($hostId)
                        );
                    } else {
                        $prefix = '';
                        switch ($relationType) {
                            case ImageRelationType::TYPE_MANUFACTURER:
                                $prefix = Image::MEDIA_TYPE_MANUFACTURER;
                                break;
                            case ImageRelationType::TYPE_CATEGORY:
                                $prefix = Image::MEDIA_TYPE_CATEGORY;
                                break;
                            case ImageRelationType::TYPE_SPECIFIC_VALUE:
                                $prefix = Image::MEDIA_TYPE_SPECIFIC_VALUE;
                                break;
                        }

                        $endpointId = Shopware()->Db()->fetchOne(
                            "SELECT image_id FROM " . $dbInfo['table'] . " WHERE host_id = ? AND image_id LIKE '{$prefix}_%'",
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
        }

        Logger::write(sprintf('Trying to get endpointId with hostId (%s) and type (%s) ... endpointId: (%s)', $hostId, $type, $endpointId), Logger::DEBUG, 'linker');
        
        return ($endpointId !== false) ? $endpointId : null;
    }

    public function save($endpointId, $hostId, $type)
    {
        Logger::write(sprintf('Save link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type), Logger::DEBUG, 'linker');

        $statement = false;
        $dbInfo = $this->getTableInfo($type);
        if ($dbInfo !== null) {
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

                    try {
                        $statement = Shopware()->Db()->query($sql, array($productId, $detailId, $hostId));
                    } catch (\Exception $e) {
                        Logger::write(sprintf(
                            'SQL: %s - Params: productId (%s), detailId (%s), hostId (%s)',
                            $sql,
                            $productId,
                            $detailId,
                            $hostId
                        ), Logger::DEBUG, 'linker');
                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'linker');
                    }
                    break;
                case IdentityLinker::TYPE_IMAGE:
                    list ($mediaType, $foreignId, $mediaId) = IdConcatenator::unlink($endpointId);

                    $sql = '';
                    try {
                        if ($mediaType === Image::MEDIA_TYPE_PRODUCT) {
                            $sql = '
                                INSERT IGNORE INTO jtl_connector_link_product_image
                                (
                                    id, host_id, image_id, media_id
                                )
                                VALUES (?,?,?,?)
                            ';
        
                            $statement = Shopware()->Db()->query($sql, array($foreignId, $hostId, $endpointId, $mediaId));
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
                    } catch (\Exception $e) {
                        Logger::write(sprintf(
                            'SQL: %s - Params: foreignId (%s), hostId (%s), endpointId (%s), mediaId (%s)',
                            $sql,
                            $foreignId,
                            $hostId,
                            $endpointId,
                            $mediaId
                        ), Logger::DEBUG, 'linker');
                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'linker');
                    }
                    break;
                case IdentityLinker::TYPE_CROSSSELLING:
                    $productId = $endpointId;
                    $splitted = IdConcatenator::unlink($endpointId);
                    if(count($splitted) == 2) {
                        $productId = $splitted[1];
                    }

                    $sql = '
                        INSERT IGNORE INTO ' . $dbInfo['table'] . '
                        (
                            ' . $dbInfo['pk'] . ', host_id
                        )
                        VALUES (?,?)
                    ';

                    try {
                        $statement = Shopware()->Db()->query($sql, array($productId, $hostId));
                    } catch (\Exception $e) {
                        Logger::write(sprintf(
                            'SQL: %s - Params: productId (%s), hostId (%s)',
                            $sql,
                            $productId,
                            $hostId
                        ), Logger::DEBUG, 'linker');
                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'linker');
                    }
                    break;
                case IdentityLinker::TYPE_CROSSSELLING_GROUP:
                    $sql = 'UPDATE jtl_connector_crosssellinggroup SET host_id = ? WHERE id = ?';
                    $statement = Shopware()->Db()->query($sql, array($hostId, $endpointId));
                    break;
                default:
                    $sql = '
                        INSERT IGNORE INTO ' . $dbInfo['table'] . '
                        (
                            ' . $dbInfo['pk'] . ', host_id
                        )
                        VALUES (?,?)
                    ';

                    try {
                        $statement = Shopware()->Db()->query($sql, array($endpointId, $hostId));
                    } catch (\Exception $e) {
                        Logger::write(sprintf(
                            'SQL: %s - Params: endpointId (%s), hostId (%s)',
                            $sql,
                            $endpointId,
                            $hostId
                        ), Logger::DEBUG, 'linker');
                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'linker');
                    }
                    break;
            }
        }

        return $statement ? true : false;
    }

    public function delete($endpointId = null, $hostId = null, $type)
    {
        Logger::write(sprintf('Delete link with endpointId (%s), hostId (%s) and type (%s)', $endpointId, $hostId, $type), Logger::DEBUG, 'linker');

        $rows = false;
        $dbInfo = $this->getTableInfo($type);
        if ($dbInfo !== null) {
            $where = [];

            if ($endpointId) {
                switch ($type) {
                    case IdentityLinker::TYPE_PRODUCT:
                        list ($detailId, $productId) = IdConcatenator::unlink($endpointId);

                        $where = array('product_id = ?' => $productId, 'detail_id = ?' => $detailId);
                        break;
                    case IdentityLinker::TYPE_IMAGE:
                        list ($mediaType, $foreignId, $mediaId) = IdConcatenator::unlink($endpointId);

                        $where = array($dbInfo['pk'] . ' = ?' => $mediaId);
                        if ($mediaType === Image::MEDIA_TYPE_PRODUCT) {
                            $where = array('id = ?' => $foreignId);
                            $dbInfo['table'] = 'jtl_connector_link_product_image';
                        }

                        /*
                        $where = ($mediaType === Image::MEDIA_TYPE_PRODUCT) ?
                            array('id = ?' => $foreignId) : array($dbInfo['pk'] . ' = ?' => $mediaId);
                        */
                        break;
                    case IdentityLinker::TYPE_CROSSSELLING_GROUP:
                        $sql = 'UPDATE jtl_connector_crosssellinggroup SET host_id = 0 WHERE id = ?';
                        
                        try {
                            $statement = Shopware()->Db()->query($sql, array($endpointId));
                        } catch (\Exception $e) {
                            Logger::write(sprintf(
                                'SQL: %s - Params: endpointId (%s)',
                                $sql,
                                $endpointId
                            ), Logger::DEBUG, 'linker');
                            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'linker');
                        }

                        return $statement ? true : false;
                    default:
                        $where = array($dbInfo['pk'] . ' = ?' => $endpointId);
                        break;
                }
            }

            if ($hostId) {
                // Cannot delete in product image table if ony hostId is set cause of missing mediaType

                $where += array('host_id = ?' => $hostId);
            }

            $rows = Shopware()->Db()->delete($dbInfo['table'], $where);
        }

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
             TRUNCATE TABLE jtl_connector_link_specific;
             TRUNCATE TABLE jtl_connector_link_specific_value;
             TRUNCATE TABLE jtl_connector_link_payment;
             TRUNCATE TABLE jtl_connector_crossselling;
             UPDATE jtl_connector_crosssellinggroup SET host_id = 0;
             '
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
                return [
                    'table' => 'jtl_connector_link_category',
                    'pk' => 'category_id'
                ];
            case IdentityLinker::TYPE_CUSTOMER:
                return [
                    'table' => 'jtl_connector_link_customer',
                    'pk' => 'customer_id'
                ];
            case IdentityLinker::TYPE_PRODUCT:
                return [
                    'table' => 'jtl_connector_link_detail',
                    'pk' => 'product_id'
                ];
            case IdentityLinker::TYPE_IMAGE:
                return [
                    'table' => 'jtl_connector_link_image',
                    'pk' => 'media_id'
                ];
            case IdentityLinker::TYPE_MANUFACTURER:
                return [
                    'table' => 'jtl_connector_link_manufacturer',
                    'pk' => 'manufacturer_id'
                ];
            case IdentityLinker::TYPE_DELIVERY_NOTE:
                return [
                    'table' => 'jtl_connector_link_note',
                    'pk' => 'note_id'
                ];
            case IdentityLinker::TYPE_CUSTOMER_ORDER:
                return [
                    'table' => 'jtl_connector_link_order',
                    'pk' => 'order_id'
                ];
            case IdentityLinker::TYPE_SPECIFIC:
                return [
                    'table' => 'jtl_connector_link_specific',
                    'pk' => 'specific_id'
                ];
            case IdentityLinker::TYPE_SPECIFIC_VALUE:
                return [
                    'table' => 'jtl_connector_link_specific_value',
                    'pk' => 'specific_value_id'
                ];
            case IdentityLinker::TYPE_PAYMENT:
                return [
                    'table' => 'jtl_connector_link_payment',
                    'pk' => 'order_id'
                ];
            case IdentityLinker::TYPE_CROSSSELLING:
                return [
                    'table' => 'jtl_connector_crossselling',
                    'pk' => 'product_id'
                ];
            case IdentityLinker::TYPE_CROSSSELLING_GROUP:
                return [
                    'table' => 'jtl_connector_crosssellinggroup',
                    'pk' => 'id'
                ];
            case IdentityLinker::TYPE_TAX_CLASS:
                return [
                    'table' => 'jtl_connector_link_tax_class',
                    'pk' => 'tax_id'
                ];
        }

        return null;
    }
}
