<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ConfigGroupI18n as ConfigGroupI18nModel;

/**
 * ConfigGroupI18n Model
 * @access public
 */
class ConfigGroupI18n extends ConfigGroupI18nModel
{
    protected $fields = array(
        'configGroupId' => '',
        'languageISO' => '',
        'name' => '',
        'description' => ''
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
