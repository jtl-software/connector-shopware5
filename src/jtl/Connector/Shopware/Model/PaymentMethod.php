<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\PaymentMethod as PaymentMethodModel;

/**
 * PaymentMethod Model
 * @access public
 */
class PaymentMethod extends PaymentMethodModel
{
    protected $fields = array(
        'id' => '',
        'sort' => '',
        'moduleId' => '',
        'picture' => '',
        'vendor' => '',
        'useMail' => '',
        'isActive' => '',
        'isUseable' => ''
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
