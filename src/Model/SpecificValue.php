<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\SpecificValue as SpecificValueModel;

/**
 * SpecificValue Model
 * @access public
 */
class SpecificValue extends SpecificValueModel
{
    protected $fields = array(
        'id' => 'id',
        'specificId' => 'optionId',
        'sort' => 'position'
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
