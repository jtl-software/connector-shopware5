<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\DeliveryNote as DeliveryNoteModel;
use \Shopware\Models\Order\Document\Document as DocumentSW;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Mmc;

class DeliveryNote extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Order\Document\Document')->find($id);
    }

    public function findType($name)
    {
        return $this->Manager()->getRepository('Shopware\Models\Order\Document\Type')->findOneBy(array('name' => $name));
    }
    
    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(array(
                'documents'
            ))
            //->from('Shopware\Models\Order\Document\Document', 'documents')
            //->leftJoin('jtl\Connector\Shopware\Model\ConnectorLink', 'link', \Doctrine\ORM\Query\Expr\Join::WITH, 'documents.id = link.endpointId AND link.type = 29')
            ->from('jtl\Connector\Shopware\Model\Linker\DeliveryNote', 'documents')
            ->leftJoin('documents.linker', 'linker')
            ->where('documents.type = 2')
            ->andWhere('documents.documentId = 20001')
            ->andWhere('linker.hostId IS NULL')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            //->getQuery();
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = false);

        //$res = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        //return $count ? count($res) : $res;

        return $count ? ($paginator->count()) : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(DeliveryNoteModel $deliveryNote)
    {
        $result = new DeliveryNoteModel;

        $this->deleteDeliveryNoteData($deliveryNote);

        // Result
        $result->setId(new Identity('', $deliveryNote->getId()->getHost()));

        return $result;
    }

    public function save(DeliveryNoteModel $deliveryNote)
    {
        $deliveryNoteSW = null;
        $result = new DeliveryNoteModel;

        $this->prepareDeliveryNoteAssociatedData($deliveryNote, $deliveryNoteSW);

        $violations = $this->Manager()->validate($deliveryNoteSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($deliveryNoteSW);
        $this->flush();

        // Result
        $result->setId(new Identity($deliveryNoteSW->getId(), $deliveryNote->getId()->getHost()));

        return $result;
    }

    protected function deleteDeliveryNoteData(DeliveryNoteModel $deliveryNote)
    {
        $deliveryNoteId = (strlen($deliveryNote->getId()->getEndpoint()) > 0) ? (int) $deliveryNote->getId()->getEndpoint() : null;

        if ($deliveryNoteId !== null && $deliveryNoteId > 0) {
            $deliveryNoteSW = $this->find((int) $deliveryNoteId);
            if ($deliveryNoteSW !== null) {
                $this->Manager()->remove($deliveryNoteSW);
                $this->Manager()->flush($deliveryNoteSW);
            }
        }
    }

    protected function prepareDeliveryNoteAssociatedData(DeliveryNoteModel $deliveryNote, DocumentSW &$deliveryNoteSW = null)
    {
        $deliveryNoteId = (strlen($deliveryNote->getId()->getEndpoint()) > 0) ? (int) $deliveryNote->getId()->getEndpoint() : null;

        if ($deliveryNoteId !== null && $deliveryNoteId > 0) {
            $deliveryNoteSW = $this->find($deliveryNoteId);
        }

        $orderMapper = Mmc::getMapper('CustomerOrder');
        $orderSW = $orderMapper->find($deliveryNote->getCustomerOrderId()->getEndpoint());

        if ($orderSW !== null) {
            if ($deliveryNoteSW === null) {
                $deliveryNoteSW = new DocumentSW;
            }

            $type = $this->findType('Lieferschein');
            $amount = $orderSW->getNet() == 0 ? $orderSW->getInvoiceAmount() : $orderSW->getInvoiceAmountNet();

            $deliveryNoteSW->setDate($deliveryNote->getCreationDate())
                    ->setCustomerId($orderSW->getCustomer()->getId())
                    ->setOrderId($orderSW->getId())
                    ->setAmount($amount)
                    ->setHash(md5(uniqid(rand())))
                    ->setDocumentId($orderSW->getNumber());

            $deliveryNoteSW->setType($type);
            $deliveryNoteSW->setOrder($orderSW);
        }
    }
}
