<?php

namespace CreditMergeBundle\Tests\Service;

use CreditMergeBundle\Service\CreditMergeStatsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CreditMergeStatsService::class)]
#[RunTestsInSeparateProcesses]
final class CreditMergeStatsServiceTest extends AbstractIntegrationTestCase
{
    private CreditMergeStatsService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(CreditMergeStatsService::class);
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(CreditMergeStatsService::class, $this->service);
    }
}
