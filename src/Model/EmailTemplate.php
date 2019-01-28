<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\EmailTemplate as EmailTemplateModel;

/**
 * EmailTemplate Model
 * @access public
 */
class EmailTemplate extends EmailTemplateModel
{
    protected $fields = array(
        'id' => '',
        'name' => '',
        'description' => '',
        'emailType' => '',
        'moduleId' => '',
        'filename' => '',
        'isActive' => '',
        'isOii' => '',
        'isAgb' => '',
        'isWrb' => '',
        'error' => ''
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
