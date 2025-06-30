<?php

namespace CreditMergeBundle\Tests\Unit\Tests\Mock;

use CreditMergeBundle\Tests\Mock\AccountInterface;
use PHPUnit\Framework\TestCase;

class AccountInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(AccountInterface::class));
    }
    
    public function testInterfaceMethods(): void
    {
        $reflection = new \ReflectionClass(AccountInterface::class);
        
        $this->assertTrue($reflection->hasMethod('getId'));
        $this->assertTrue($reflection->hasMethod('getName'));
        $this->assertTrue($reflection->hasMethod('getCurrency'));
    }
}