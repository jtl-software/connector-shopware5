<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\SpecificValueI18n as SpecificValueI18nModel;

/**
 * SpecificValueI18n Model
 * @access public
 */
class SpecificValueI18n extends SpecificValueI18nModel
{
    protected $fields = array(
        'languageISO' => '',
        'specificValueId' => 'specificValueId',
        'value' => 'value',
        'urlPath' => '',
        'description' => '',
        'metaDescription' => '',
        'metaKeywords' => '',
        'titleTag' => ''
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
