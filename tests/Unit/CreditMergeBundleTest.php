<?php

namespace CreditMergeBundle\Tests\Unit;

use CreditMergeBundle\CreditMergeBundle;
use PHPUnit\Framework\TestCase;

class CreditMergeBundleTest extends TestCase
{
    public function testGetBundleDependencies(): void
    {
        $dependencies = CreditMergeBundle::getBundleDependencies();
        
        $this->assertArrayHasKey(\CreditBundle\CreditBundle::class, $dependencies);
        $this->assertArrayHasKey(\Tourze\Symfony\CronJob\CronJobBundle::class, $dependencies);
        $this->assertEquals(['all' => true], $dependencies[\CreditBundle\CreditBundle::class]);
        $this->assertEquals(['all' => true], $dependencies[\Tourze\Symfony\CronJob\CronJobBundle::class]);
    }
    
    public function testBundleInstantiation(): void
    {
        $bundle = new CreditMergeBundle();
        $this->assertInstanceOf(CreditMergeBundle::class, $bundle);
    }
}