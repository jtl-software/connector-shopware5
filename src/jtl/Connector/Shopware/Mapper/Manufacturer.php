<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\Manufacturer as ManufacturerModel;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Model\Identity;
use \Shopware\Models\Article\Supplier as ManufacturerSW;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

class Manufacturer extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Article\Supplier', $id);
    }
    
    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->findOneBy($kv);
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'supplier',
                'attribute'
            )
            //->from('Shopware\Models\Article\Supplier', 'supplier')
            //->leftJoin('jtl\Connector\Shopware\Model\ConnectorLink', 'link', \Doctrine\ORM\Query\Expr\Join::WITH, 'supplier.id = link.endpointId AND link.type = 41')
            ->from('jtl\Connector\Shopware\Model\Linker\Manufacturer', 'supplier')
            ->leftJoin('supplier.linker', 'linker')
            ->leftJoin('supplier.attribute', 'attribute')
            ->where('linker.hostId IS NULL')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            //->getQuery();
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        //$res = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        //return $count ? count($res) : $res;

        return $count ? ($paginator->count()) : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(ManufacturerModel $manufacturer)
    {
        $result = new ManufacturerModel;

        $this->deleteManufacturerData($manufacturer);

        // Result
        $result->setId(new Identity('', $manufacturer->getId()->getHost()));

        return $result;
    }

    public function save(ManufacturerModel $manufacturer)
    {
        $manufacturerSW = null;
        $result = new ManufacturerModel;

        $this->prepareManufacturerAssociatedData($manufacturer, $manufacturerSW);
        $this->prepareI18nAssociatedData($manufacturer, $manufacturerSW);

        $violations = $this->Manager()->validate($manufacturerSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($manufacturerSW);
        $this->flush();

        // Result
        $result->setId(new Identity($manufacturerSW->getId(), $manufacturer->getId()->getHost()));

        return $result;
    }

    protected function deleteManufacturerData(ManufacturerModel $manufacturer)
    {
        $manufacturerId = (strlen($manufacturer->getId()->getEndpoint()) > 0) ? (int) $manufacturer->getId()->getEndpoint() : null;

        if ($manufacturerId !== null && $manufacturerId > 0) {
            $manufacturerSW = $this->find((int) $manufacturerId);
            if ($manufacturerSW !== null) {
                $this->Manager()->remove($manufacturerSW);
                $this->Manager()->flush($manufacturerSW);
            }
        }
    }

    protected function prepareManufacturerAssociatedData(ManufacturerModel &$manufacturer, ManufacturerSW &$manufacturerSW = null)
    {
        $manufacturerId = (strlen($manufacturer->getId()->getEndpoint()) > 0) ? (int)$manufacturer->getId()->getEndpoint() : null;

        if ($manufacturerId !== null && $manufacturerId > 0) {
            $manufacturerSW = $this->find($manufacturerId);
        }

        if ($manufacturerSW === null) {
            $manufacturerSW = new ManufacturerSW;
        }

        $manufacturerSW->setName($manufacturer->getName())
            ->setLink($manufacturer->getWebsiteUrl());
    }

    protected function prepareI18nAssociatedData(ManufacturerModel &$manufacturer, ManufacturerSW &$manufacturerSW)
    {
        foreach ($manufacturer->getI18ns() as $i18n) {
            if ($i18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                $manufacturerSW->setDescription($i18n->getDescription());
                $manufacturerSW->setMetaTitle($i18n->getTitleTag());
                $manufacturerSW->setMetaDescription($i18n->getMetaDescription());
                $manufacturerSW->setMetaKeywords($i18n->getMetaKeywords());
            }
        }
    }
}
