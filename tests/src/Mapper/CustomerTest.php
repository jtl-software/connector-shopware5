<?php

namespace jtl\Connector\Shopware\Tests\Mapper;

use jtl\Connector\Shopware\Mapper\Customer;
use jtl\Connector\Model\Customer as CustomerModel;
use jtl\Connector\Shopware\Tests\TestCase;
use Shopware\Components\Model\ModelManager;

class CustomerTest extends TestCase
{
    /**
     * @dataProvider findDataProvider
     */
    public function testFind($id, $expectedResult)
    {
        $mapper = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->setMethods(['getManager'])
            ->getMock();

        $modelManagerMock = $this->getMockBuilder(ModelManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['find'])
            ->getMock();

        $modelManagerMock->expects(in_array($id, [0, null]) ? $this->never() : $this->once())
            ->method('find')
            ->with('Shopware\Models\Customer\Customer', $id);

        $mapper
            ->expects(in_array($id, [0, null]) ? $this->never() : $this->once())
            ->method('getManager')
            ->willReturn($modelManagerMock);

        $mapper->find($id);
    }

    public function findDataProvider()
    {
        return [
            [
                0, null
            ],
            [
                1, 1
            ]
        ];
    }

    public function testFetchCount()
    {
        $limit = rand(0, 100);

        $customerMapperMock = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->setMethods(["findAll"])
            ->getMock();

        $customerMapperMock
            ->expects($this->once())
            ->method("findAll")
            ->with($limit, true)
            ->willReturn(true);

        $result = $this->invokeMethodFromObject($customerMapperMock, "fetchCount", $limit);

        $this->assertTrue($result);
    }

    public function testDelete()
    {
        $id = rand(0, 100);

        $customer = (new CustomerModel);
        $customer->getId()->setHost($id);

        $customerMapperMock = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->setMethods(["deleteCustomerData"])
            ->getMock();

        $customerMapperMock
            ->expects($this->once())
            ->method("deleteCustomerData")
            ->with($customer);


        $result = $customerMapperMock->delete($customer);

        $this->assertEquals($result, $customer);
    }
}