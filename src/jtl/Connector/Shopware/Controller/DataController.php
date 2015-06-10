<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Core\Controller\Controller as CoreController;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Model\Statistic;
use \jtl\Connector\Core\Utilities\ClassName;
use \jtl\Connector\Model\DataModel;
use \jtl\Connector\Core\Model\DataModel as CoreDataModel;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;

/**
 * Product Controller
 * @access public
 */
abstract class DataController extends CoreController
{
    /**
     * Statistic
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function statistic(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $class = ClassName::getFromNS(get_called_class());
            
            $statModel = new Statistic();
            $mapper = Mmc::getMapper($class);

            $statModel->setAvailable($mapper->fetchCount());

            /*
            if (is_callable(array($mapper, 'fetchPendingCount'))) {
                $statModel->setPending($mapper->fetchPendingCount());
            }
            */

            $statModel->setControllerName(lcfirst($class));

            $action->setResult($statModel->getPublic());
        } catch (\Exception $exc) {
            $action->setError($this->handleException($exc));
        }

        return $action;
    }

    /**
     * Insert or update
     *
     * @param \jtl\Connector\Core\Model\DataModel $model
     * @return \jtl\Connector\Result\Action
     */
    public function push(CoreDataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $class = ClassName::getFromNS(get_called_class());

            $mapper = Mmc::getMapper($class);
            $res = $mapper->save($model);
            
            //$action->setResult($res->getPublic());
            $action->setResult($res);
        } catch (\Exception $exc) {
            /*
            if (!Shopware()->Models()->isOpen()) {
                $conn = Shopware()->Models()->getConnection();
                $config = Shopware()->Models()->getConfiguration();

                Shopware()->Container()->Models = \Shopware\Components\Model\ModelManager::createInstance($conn, $config, null);
            }
            */

            $action->setError($this->handleException($exc));
        }

        return $action;
    }
    
    /**
     * Select
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $result = array();
            $limit = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;

            $class = ClassName::getFromNS(get_called_class());

            $mapper = Mmc::getMapper($class);
            $models = $mapper->findAll($limit);

            foreach ($models as $modelSW) {
                $model = Mmc::getModel($class);
                $model->map(true, DataConverter::toObject($modelSW));

                //$result[] = $model->getPublic();
                $result[] = $model;
            }

            $action->setResult($result);
        } catch (\Exception $exc) {
            $action->setError($this->handleException($exc));
        }

        return $action;
    }
    
    /**
     * Delete
     *
     * @param \jtl\Connector\Core\Model\DataModel $model
     * @return \jtl\Connector\Result\Action
     */
    public function delete(CoreDataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $class = ClassName::getFromNS(get_called_class());

            $mapper = Mmc::getMapper($class);
            $res = $mapper->delete($model);
            
            $action->setResult($res);
        } catch (\Exception $exc) {
            $action->setError($this->handleException($exc));
        }

        return $action;
    }

    protected function handleException(\Exception $e)
    {
        Logger::write(ExceptionFormatter::format($e), Logger::WARNING, 'controller');

        $err = new Error();
        $err->setCode($e->getCode());
        $err->setMessage($e->getMessage());
        
        return $err;
    }

    /**
     * Add Subobject to Object
     *
     * @param \jtl\Connector\Model\DataModel $model
     * @param string $setter
     * @param string $className
     * @param multiple: mixed $kvs
     * @param multiple: mixed $members
     */
    protected function addPos(DataModel &$model, $setter, $className, $data, $isSeveral = false)
    {
        $callableName = get_class($model) . '::' . $setter;

        if (!is_callable(array($model, $setter), false, $callableName)) {
            throw new \InvalidArgumentException(sprintf('Method %s in class %s not found', $setter, get_class($model)));
        }
            
        if ($isSeveral) {
            foreach ($data as $swArr) {
                $subModel = Mmc::getModel($className);
                $subModel->map(true, DataConverter::toObject($swArr, true));
                $model->{$setter}($subModel);
            }
        } else {
            $subModel = Mmc::getModel($className);
            $subModel->map(true, DataConverter::toObject($data, true));
            $model->{$setter}($subModel);
        }
    }
}
