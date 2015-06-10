<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\ProductStockLevel as ProductStockLevelModel;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Exception\DatabaseException;

class ProductStockLevel extends DataMapper
{
    public function save(ProductStockLevelModel $stock)
    {
        $productId = (strlen($stock->getProductId()->getEndpoint()) > 0) ? $stock->getProductId()->getEndpoint() : null;
        if ($productId !== null) {
            list ($detailId, $productId) = IdConcatenator::unlink($productId);
            $productId = intval($productId);
            $detailId = intval($detailId);

            if ($productId > 0 && $detailId > 0) {
                $productMapper = Mmc::getMapper('Product');
                $detailSW = $productMapper->findDetail($detailId);

                if ($detailSW !== null) {
                    $detailSW->setInStock($stock->getStockLevel());

                    $this->Manager()->persist($detailSW);
                    $this->Manager()->flush();

                    return $stock;
                }
            }

            throw new DatabaseException(sprintf('Product with Endpoint Id (%s) cannot be found', $productId));
        }

        throw new DatabaseException('Product Endpoint Id cannot be empty');
    }
}
