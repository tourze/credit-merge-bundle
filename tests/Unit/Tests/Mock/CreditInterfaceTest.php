<?php

namespace CreditMergeBundle\Tests\Unit\Tests\Mock;

use CreditMergeBundle\Tests\Mock\CreditInterface;
use PHPUnit\Framework\TestCase;

class CreditInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(CreditInterface::class));
    }
    
    public function testInterfaceMethods(): void
    {
        $reflection = new \ReflectionClass(CreditInterface::class);
        
        $this->assertTrue($reflection->hasMethod('getId'));
        $this->assertTrue($reflection->hasMethod('getAmount'));
        $this->assertTrue($reflection->hasMethod('getExpiryDate'));
    }
}