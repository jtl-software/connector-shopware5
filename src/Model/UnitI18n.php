<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\UnitI18n as UnitI18nModel;

/**
 * UnitI18n Model
 * @access public
 */
class UnitI18n extends UnitI18nModel
{
    protected $fields = array(
        'unitId' => 'id',
        'languageISO' => 'localeName',
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
