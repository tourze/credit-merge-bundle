<?php

declare(strict_types=1);

namespace CreditMergeBundle\Tests\Entity;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * 合并操作记录实体测试.
 *
 * @internal
 */
#[CoversClass(MergeOperation::class)]
final class MergeOperationTest extends AbstractEntityTestCase
{
    public function testConstruct(): void
    {
        $entity = new MergeOperation();
        $this->assertInstanceOf(MergeOperation::class, $entity);
        $this->assertEquals('pending', $entity->getStatus());
        $this->assertFalse($entity->isDryRun());
    }

    protected function createEntity(): object
    {
        return new MergeOperation();
    }

    /**
     * @return \Generator<string, array{string, mixed}>
     */
    public static function propertiesProvider(): \Generator
    {
        yield 'account' => ['account', new Account()];
        yield 'operationTime' => ['operationTime', new \DateTimeImmutable()];
        yield 'timeWindowStrategy' => ['timeWindowStrategy', TimeWindowStrategy::DAY];
        yield 'minAmountThreshold' => ['minAmountThreshold', '5.00'];
        yield 'recordsCountBefore' => ['recordsCountBefore', 10];
        yield 'recordsCountAfter' => ['recordsCountAfter', 5];
        yield 'mergedRecordsCount' => ['mergedRecordsCount', 5];
        yield 'totalAmount' => ['totalAmount', '50.00'];
        yield 'batchSize' => ['batchSize', 100];
        // 跳过 isDryRun，因为它有特殊的 getter/setter 命名规则
        // yield 'isDryRun' => ['isDryRun', false];
        yield 'status' => ['status', 'success'];
        yield 'resultMessage' => ['resultMessage', 'Test message'];
        yield 'context' => ['context', ['test' => 'data']];
        yield 'executionTime' => ['executionTime', '1.500'];
    }

    public function testIsDryRunGetterAndSetter(): void
    {
        $entity = new MergeOperation();

        // 测试默认值
        $this->assertFalse($entity->isDryRun());

        // 测试 setter 和 getter
        $entity->setIsDryRun(true);
        $this->assertTrue($entity->isDryRun());

        $entity->setIsDryRun(false);
        $this->assertFalse($entity->isDryRun());
    }

    public function testToString(): void
    {
        $entity = new MergeOperation();
        $entity->setStatus('success');
        $entity->setTimeWindowStrategy(TimeWindowStrategy::DAY);
        $entity->setMergedRecordsCount(5);
        $entity->setRecordsCountBefore(10);

        // 使用反射设置ID，因为ID是私有且自动生成的
        $reflection = new \ReflectionClass($entity);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($entity, 1);

        $string = (string) $entity;
        $this->assertStringContainsString('MergeOperation #1', $string);
        $this->assertStringContainsString('[success]', $string);
        $this->assertStringContainsString('day', $string);
        $this->assertStringContainsString('5/10 records', $string);
    }

    public function testSetContext(): void
    {
        $entity = new MergeOperation();
        $context = ['merge_reason' => 'test', 'records' => [1, 2, 3]];
        $entity->setContext($context);
        $this->assertEquals($context, $entity->getContext());
    }

    public function testSetContextNull(): void
    {
        $entity = new MergeOperation();
        $entity->setContext(null);
        $this->assertNull($entity->getContext());
    }
}
