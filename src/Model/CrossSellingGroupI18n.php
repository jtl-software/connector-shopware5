<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CrossSellingGroupI18n as CrossSellingGroupI18nModel;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;

/**
 * CrossSellingGroup Model
 * @access public
 */
class CrossSellingGroupI18n extends CrossSellingGroupI18nModel
{
    protected $fields = array(
        'crossSellingGroupId' => 'group_id',
        'languageISO' => 'languageISO',
        'name' => 'name',
        'description' => 'description'
    );

    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        $obj->languageISO = LanguageUtil::map(null, null, $obj->languageISO);

        return DataModel::map($toWawi, $obj, $this);
    }
}