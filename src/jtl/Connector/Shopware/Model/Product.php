<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Product as ProductModel;

/**
 * Product Model
 * @access public
 */
class Product extends ProductModel
{
    protected $fields = array(
        'id' => 'id',
        'masterProductId' => 'masterProductId',
        'manufacturerId' => 'supplierId',
        'unitId' => 'unitId',
        'basePriceUnitId' => '',
        'shippingClassId' => '',
        //'taxClassId' => array('tax', 'id'),
        'sku' => 'number',
        'note' => '',        
        'vat' => array('tax', 'tax'),
        'minimumOrderQuantity' => 'minPurchase',
        'ean' => 'ean',
        'isTopProduct' => 'highlight',
        'productWeight' => 'weight',
        'shippingWeight' => '',
        'isNewProduct' => '',
        'recommendedRetailPrice' => '',
        'considerStock' => '',
        'permitNegativeStock' => '',
        'considerVariationStock' => '',
        'supplierStockLevel' => '',
        'isDivisible' => '',
        'considerBasePrice' => '',
        'basePriceDivisor' => '',
        'basePriceFactor' => '',
        'basePriceQuantity' => 'referenceUnit',
        'basePriceUnitCode' => array('unit', 'unit'),
        'basePriceUnitName' => array('unit', 'name'),
        //'keywords' => 'keywords',
        'sort' => '',
        'creationDate' => 'added',
        //'availableFrom' => 'availableFrom',
        'availableFrom' => 'releaseDate',
        'manufacturerNumber' => 'supplierNumber',
        'serialNumber' => '',
        'isbn' => '',
        'asin' => '',
        'unNumber' => '',
        'hazardIdNumber' => '',
        'taric' => '',
        'isMasterProduct' => 'isMasterProduct',
        'packagingQuantity' => 'purchaseSteps',
        'partsListId' => '',
        'upc' => '',
        'originCountry' => '',
        'epid' => '',
        'productTypeId' => '',
        'isBatch' => '',
        'isBestBefore' => '',
        'isSerialNumber' => '',
        'nextAvailableInflowDate' => '',
        'nextAvailableInflowQuantity' => '',
        'measurementUnitId' => array('unit', 'id'),
        'measurementQuantity' => 'purchaseUnit',
        'measurementUnitCode' => array('unit', 'unit'),
        'length' => 'len',
        'height' => 'height',
        'width' => 'width',
        'isActive' => 'active',
        'supplierDeliveryTime' => 'shippingTime'
    );
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }
}
