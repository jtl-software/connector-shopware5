<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\SpecificI18n as SpecificI18nModel;

/**
 * SpecificI18n Model
 * @access public
 */
class SpecificI18n extends SpecificI18nModel
{
    protected $fields = array(
        'languageISO' => '',
        'specificId' => 'specificId',
        'name' => 'name'
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
