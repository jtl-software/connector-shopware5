<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\EntityNotFoundException;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\DeliveryNote as DeliveryNoteModel;
use \Shopware\Models\Order\Document\Document as DocumentSW;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\Shop as ShopUtil;

class DeliveryNote extends DataMapper
{
    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->getRepository('Shopware\Models\Order\Document\Document')->find($id);
    }

    public function findType($name)
    {
        return $this->Manager()->getRepository('Shopware\Models\Order\Document\Type')->findOneBy(array('name' => $name));
    }
    
    public function findNewType($name)
    {
        return $this->Manager()->getRepository('Shopware\Models\Document\Document')->findOneBy(array('name' => $name));
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

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

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
        $result = $deliveryNote;

        $this->deleteDeliveryNoteData($deliveryNote);

        // Result
        $result->setId(new Identity('', $deliveryNote->getId()->getHost()));

        return $result;
    }

    public function save(DeliveryNoteModel $deliveryNote)
    {
        //$deliveryNoteSW = null;
        $result = $deliveryNote;

        try {
            $endpointId = 0;
            $hostId = $deliveryNote->getId()->getHost();
            $this->prepareDeliveryNoteAssociatedData($deliveryNote, $endpointId);

            // Result
            $result->setId(new Identity($endpointId, $hostId));
        } catch(EntityNotFoundException $ex){
            if (ShopUtil::isCustomerNotFoundException($ex->getMessage())) {
                Logger::write($ex->getMessage(), Logger::ERROR, Logger::CHANNEL_DATABASE);
            } else {
                throw $ex;
            }
        }

        return $result;
    }

    protected function deleteDeliveryNoteData(DeliveryNoteModel $deliveryNote)
    {
        $deliveryNoteId = (strlen($deliveryNote->getId()->getEndpoint()) > 0) ? (int) $deliveryNote->getId()->getEndpoint() : null;

        if ($deliveryNoteId !== null && $deliveryNoteId > 0) {
            /*
            $deliveryNoteSW = $this->find((int) $deliveryNoteId);
            if ($deliveryNoteSW !== null) {
            */
    
                /** @var \Doctrine\DBAL\Connection $connection */
                $connection = Shopware()->Container()->get('dbal_connection');
                $queryBuilder = $connection->createQueryBuilder();
    
                $documentHash = $queryBuilder->select('hash')
                    ->from('s_order_documents')
                    ->where('id = :documentId')
                    ->setParameter('documentId', $deliveryNoteId)
                    ->execute()
                    ->fetchColumn();
    
                $queryBuilder = $connection->createQueryBuilder();
                $queryBuilder->delete('s_order_documents')
                    ->where('id = :documentId')
                    ->setParameter('documentId', $deliveryNoteId)
                    ->execute();
    
                $sw = Shopware();
                $documentPath = '';
                if (version_compare(ShopUtil::version(), '5.3.0', '<')) {
                    $documentPath = $sw->DocPath() . 'files/documents' . DIRECTORY_SEPARATOR;
                } elseif (version_compare(ShopUtil::version(), '5.4.0', '<')) {
                    $documentPath = rtrim($sw->DocPath('files_documents'), '/') . DIRECTORY_SEPARATOR;
                } else {
                    try {
                        $documentPath = rtrim($sw->Container()->getParameter('shopware.app.documentsdir'), '/') . DIRECTORY_SEPARATOR;
                    } catch (\Exception $e) {
                        return;
                    }
                }
                
                $file = $documentPath . $documentHash . '.pdf';
                if (!is_file($file)) {
                    return;
                }
    
                unlink($file);
                
                /*
                $this->Manager()->remove($deliveryNoteSW);
                $this->Manager()->flush($deliveryNoteSW);
                */
            //}
        }
    }

    //protected function prepareDeliveryNoteAssociatedData(DeliveryNoteModel $deliveryNote, DocumentSW &$deliveryNoteSW = null)
    protected function prepareDeliveryNoteAssociatedData(DeliveryNoteModel &$deliveryNote, &$endpointId)
    {
        /*
        $deliveryNoteId = (strlen($deliveryNote->getId()->getEndpoint()) > 0) ? (int) $deliveryNote->getId()->getEndpoint() : null;

        if ($deliveryNoteId !== null && $deliveryNoteId > 0) {
            $deliveryNoteSW = $this->find($deliveryNoteId);
        }
        */

        $orderMapper = Mmc::getMapper('CustomerOrder');
        
        /** @var \Shopware\Models\Order\Order $orderSW */
        $orderSW = $orderMapper->find($deliveryNote->getCustomerOrderId()->getEndpoint());

        if (is_null($orderSW)) {
            return;
        }
    
        $deliveryNote->getCustomerOrderId()->setEndpoint($orderSW->getId());
        
        // Tracking
        if (count($deliveryNote->getTrackingLists()) > 0) {
            $trackingLists = $deliveryNote->getTrackingLists();
            $codes = $trackingLists[0]->getCodes();
    
            if (count($codes) > 0) {
                $orderSW->setTrackingCode($codes[0]);
                $this->Manager()->persist($orderSW);
                $this->Manager()->flush($orderSW);
            }
        }

        $createDeliveryNote = Application()->getConfig()->get('delivery_note.push.create_document', true);

        if ($createDeliveryNote === true) {
            /** @var \Shopware\Models\Document\Document $document */
            $document = Shopware()->Models()->getRepository(\Shopware\Models\Document\Document::class)->find(2);
            if (!is_null($document)) {

                try {
                    // Create order document
                    $document = \Shopware_Components_Document::initDocument(
                        $orderSW->getId(),
                        $document->getId(),
                        [
                            'netto' => false,
                            'bid' => '',
                            'voucher' => null,
                            'date' => $deliveryNote->getCreationDate()->format('d.m.Y'),
                            'delivery_date' => $deliveryNote->getCreationDate()->format('d.m.Y'),
                            'shippingCostsAsPosition' => 0,
                            '_renderer' => 'pdf',
                            '_preview' => false,
                            '_previewForcePagebreak' => '',
                            '_previewSample' => '',
                            'docComment' => $deliveryNote->getNote(),
                            'forceTaxCheck' => false,
                        ]
                    );

                    $document->render();
                } catch (\Exception $e) {
                    Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                }
            }
        }

        if (version_compare(ShopUtil::version(), '5.2.25', '<')) {
            try {
                $prop = new \ReflectionProperty(get_class($document), '_documentRowID');
                $prop->setAccessible(true);
                $endpointId = $prop->getValue($document);
            } catch (\Exception $e) {
                Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
            }
        } else {
            $endpointId = $document->_documentRowID;
        }
    }
}
