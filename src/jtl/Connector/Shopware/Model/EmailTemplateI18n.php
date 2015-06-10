<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\EmailTemplateI18n as EmailTemplateI18nModel;

/**
 * EmailTemplateI18n Model
 * @access public
 */
class EmailTemplateI18n extends EmailTemplateI18nModel
{
    protected $fields = array(
        'emailTemplateId' => '',
        'languageISO' => '',
        'subject' => '',
        'contentHtml' => '',
        'contentText' => '',
        'pdf' => '',
        'filename' => ''
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
