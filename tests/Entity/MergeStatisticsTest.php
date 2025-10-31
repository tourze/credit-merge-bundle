<?php

declare(strict_types=1);

namespace CreditMergeBundle\Tests\Entity;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeStatistics;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * 合并统计历史实体测试.
 *
 * @internal
 */
#[CoversClass(MergeStatistics::class)]
final class MergeStatisticsTest extends AbstractEntityTestCase
{
    public function testConstruct(): void
    {
        $entity = new MergeStatistics();
        $this->assertInstanceOf(MergeStatistics::class, $entity);
        $this->assertEquals(0, $entity->getTotalSmallRecords());
        $this->assertEquals(0, $entity->getMergeableRecords());
        $this->assertEquals(0, $entity->getPotentialRecordReduction());
        $this->assertEquals(0, $entity->getTimeWindowGroups());
        $this->assertEquals('0.00', $entity->getTotalSmallAmount());
        $this->assertEquals('0.00', $entity->getMergeEfficiency());
        $this->assertEquals('0.00', $entity->getAverageAmount());
    }

    protected function createEntity(): object
    {
        return new MergeStatistics();
    }

    /**
     * @return \Generator<string, array{string, mixed}>
     */
    public static function propertiesProvider(): \Generator
    {
        yield 'account' => ['account', new Account()];
        yield 'statisticsTime' => ['statisticsTime', new \DateTimeImmutable()];
        yield 'timeWindowStrategy' => ['timeWindowStrategy', TimeWindowStrategy::MONTH];
        yield 'minAmountThreshold' => ['minAmountThreshold', '10.00'];
        yield 'totalSmallRecords' => ['totalSmallRecords', 50];
        yield 'totalSmallAmount' => ['totalSmallAmount', '500.00'];
        yield 'mergeableRecords' => ['mergeableRecords', 30];
        yield 'potentialRecordReduction' => ['potentialRecordReduction', 25];
        yield 'mergeEfficiency' => ['mergeEfficiency', '85.50'];
        yield 'averageAmount' => ['averageAmount', '10.00'];
        yield 'groupStats' => ['groupStats', ['group1' => ['count' => 10]]];
        yield 'timeWindowGroups' => ['timeWindowGroups', 5];
        yield 'context' => ['context', ['analysis_type' => 'test']];
    }

    public function testToString(): void
    {
        $entity = new MergeStatistics();
        $entity->setTimeWindowStrategy(TimeWindowStrategy::WEEK);
        $entity->setTotalSmallRecords(100);
        $entity->setMergeEfficiency('85.50');

        // 使用反射设置ID，因为ID是私有且自动生成的
        $reflection = new \ReflectionClass($entity);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($entity, 1);

        $string = (string) $entity;
        $this->assertStringContainsString('MergeStatistics #1', $string);
        $this->assertStringContainsString('[week]', $string);
        $this->assertStringContainsString('100 records', $string);
        $this->assertStringContainsString('85.50% efficiency', $string);
    }

    public function testSetGroupStats(): void
    {
        $entity = new MergeStatistics();
        $groupStats = [
            '2024-01' => ['record_count' => 10, 'total_amount' => '100.00'],
            '2024-02' => ['record_count' => 15, 'total_amount' => '150.00'],
        ];
        $entity->setGroupStats($groupStats);
        $this->assertEquals($groupStats, $entity->getGroupStats());
    }

    public function testSetGroupStatsNull(): void
    {
        $entity = new MergeStatistics();
        $entity->setGroupStats(null);
        $this->assertNull($entity->getGroupStats());
    }

    public function testSetContext(): void
    {
        $entity = new MergeStatistics();
        $context = [
            'analysis_type' => 'periodic',
            'data_range' => ['start' => '2024-01-01', 'end' => '2024-01-31'],
        ];
        $entity->setContext($context);
        $this->assertEquals($context, $entity->getContext());
    }

    public function testSetContextNull(): void
    {
        $entity = new MergeStatistics();
        $entity->setContext(null);
        $this->assertNull($entity->getContext());
    }
}
