<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Currency as CurrencyModel;

/**
 * Currency Model
 * @access public
 */
class Currency extends CurrencyModel
{
    protected $fields = array(
        'id' => 'id',
        'name' => 'name',
        'iso' => 'currency',
        'nameHtml' => 'symbol',
        'factor' => 'factor',
        'isDefault' => 'default',
        //'hasCurrencySignBeforeValue' => 'hasCurrencySignBeforeValue',
        'delimiterCent' => '',
        'delimiterThousand' => ''
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
