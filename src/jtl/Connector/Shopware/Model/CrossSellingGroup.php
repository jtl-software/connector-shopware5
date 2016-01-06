<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CrossSellingGroup as CrossSellingGroupModel;

/**
 * CrossSellingGroup Model
 * @access public
 */
class CrossSellingGroup extends CrossSellingGroupModel
{
    const RELATED = 'sw_related';
    const SIMILAR = 'sw_similar';

    protected $fields = array(
        'id' => 'id'
    );

    /**
     * @return string
     */
    public function getTable()
    {
        if (count($this->getI18ns()) > 0) {
            foreach ($this->getI18ns() as $i18n) {
                if ($i18n->getName() === self::SIMILAR) {
                    return 's_articles_similar';
                }
            }
        }

        return 's_articles_relationships';
    }
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }
}
