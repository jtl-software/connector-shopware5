<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Language as LanguageModel;

/**
 * Language Model
 * @access public
 */
class Language extends LanguageModel
{
    protected $fields = array(
        'id' => 'id',
        'nameEnglish' => 'language',
        'nameGerman' => 'language',
        'languageISO' => 'locale',
        'isDefault' => 'default'
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
