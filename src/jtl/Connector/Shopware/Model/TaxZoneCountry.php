<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\TaxZoneCountry as TaxZoneCountryModel;

/**
 * TaxZoneCountry Model
 * @access public
 */
class TaxZoneCountry extends TaxZoneCountryModel
{
    protected $fields = array(
        'id' => '',
        'taxZoneId' => '',
        'countryIso' => ''
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
