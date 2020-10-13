<?php
namespace jtl\Connector\Shopware\Tests\src\Mapper;

use jtl\Connector\Shopware\Mapper\Customer;
use jtl\Connector\Shopware\Tests\src\TestCase;

class CustomerTest extends TestCase
{
    public function testFind(){
        $mapperMock = $this->getMockBuilder(Customer::class)
            ->getMock();
        
        $mapperMock->method()
    }
}