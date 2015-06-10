<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\MediaFileI18n as MediaFileI18nModel;

/**
 * MediaFileI18n Model
 * @access public
 */
class MediaFileI18n extends MediaFileI18nModel
{
    protected $fields = array(
        'mediaFileId' => '',
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
