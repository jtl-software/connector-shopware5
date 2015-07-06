<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CategoryI18n as CategoryI18nModel;

/**
 * CategoryI18n Model
 * @access public
 */
class CategoryI18n extends CategoryI18nModel
{
    protected $fields = array(
        'languageISO' => 'localeName',
        'categoryId' => 'id',
        'name' => 'name',
        'url' => '',
        'description' => 'cmsText',
        'metaDescription' => 'metaDescription',
        'metaKeywords' => 'metaKeywords',
        'titleTag' => 'cmsHeadline'
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
