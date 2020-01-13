<?php
namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ImageI18n as CoreImageI18n;
use jtl\Connector\Shopware\Model\DataModel;

/**
 * Class ImageI18n
 * @package Shopware\Plugins\Community\Frontend\jtlconnector\src\Model
 */
class ImageI18n extends CoreImageI18n
{
    /**
     * @var array
     */
    protected $fields = [
        'id' => '',
        'imageId' => '',
        'languageISO' => '',
        'altText' => 'description',
    ];

    /**
     * @param bool $toWawi
     * @param \stdClass|null $obj
     * @return bool|\stdClass|void
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }
}