<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ConfigGroup as ConfigGroupModel;

/**
 * ConfigGroup Model
 * @access public
 */
class ConfigGroup extends ConfigGroupModel
{
    protected $fields = array(
        'id' => '',
        'imagePath' => '',
        'minimumSelection' => '',
        'maximumSelection' => '',
        'type' => '',
        'sort' => '',
        'comment' => ''
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
