<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Entity\MergeStatistics;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Repository\MergeOperationRepository;
use CreditMergeBundle\Repository\MergeStatisticsRepository;
use CreditMergeBundle\Service\MergeOperationRecordService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MergeOperationRecordService::class)]
#[RunTestsInSeparateProcesses]
final class MergeOperationRecordServiceTest extends AbstractIntegrationTestCase
{
    private MergeOperationRecordService $service;

    private EntityManagerInterface&MockObject $mockEntityManager;

    private MergeOperationRepository&MockObject $mockMergeOperationRepository;

    private MergeStatisticsRepository&MockObject $mockMergeStatisticsRepository;

    private LoggerInterface&MockObject $mockLogger;

    protected function onSetUp(): void
    {
        $this->mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->mockMergeOperationRepository = $this->createMock(MergeOperationRepository::class);
        $this->mockMergeStatisticsRepository = $this->createMock(MergeStatisticsRepository::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        // 直接实例化服务并注入Mock依赖
        // 由于需要Mock EntityManager等核心服务，使用直接实例化
        /* @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $this->service = new MergeOperationRecordService(
            $this->mockEntityManager,
            $this->mockMergeOperationRepository,
            $this->mockMergeStatisticsRepository,
            $this->mockLogger
        );
    }

    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(MergeOperationRecordService::class, $this->service);
    }

    /**
     * 测试开始操作记录.
     */
    public function testStartOperation(): void
    {
        $account = new Account();
        $strategy = TimeWindowStrategy::DAY;
        $threshold = '5.00';
        $batchSize = 100;
        $isDryRun = false;

        // 设置期望
        $this->mockEntityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(MergeOperation::class))
        ;

        $this->mockEntityManager->expects($this->once())
            ->method('flush')
        ;

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('开始记录合并操作', self::callback(fn ($v) => is_array($v)))
        ;

        // 执行测试
        $operation = $this->service->startOperation($account, $strategy, $threshold, $batchSize, $isDryRun);

        // 验证结果
        $this->assertInstanceOf(MergeOperation::class, $operation);
        $this->assertSame($account, $operation->getAccount());
        $this->assertSame($strategy, $operation->getTimeWindowStrategy());
        $this->assertEquals($threshold, $operation->getMinAmountThreshold());
        $this->assertEquals($batchSize, $operation->getBatchSize());
        $this->assertEquals($isDryRun, $operation->isDryRun());
        $this->assertEquals('running', $operation->getStatus());
        $this->assertEquals(0, $operation->getRecordsCountBefore());
        $this->assertEquals(0, $operation->getRecordsCountAfter());
        $this->assertEquals(0, $operation->getMergedRecordsCount());
        $this->assertEquals('0.00', $operation->getTotalAmount());
    }

    /**
     * 测试开始操作记录（试运行模式）.
     */
    public function testStartOperationWithDryRun(): void
    {
        $account = new Account();
        $strategy = TimeWindowStrategy::WEEK;
        $threshold = '10.00';
        $batchSize = 50;
        $isDryRun = true;

        $this->mockEntityManager->expects($this->once())->method('persist');
        $this->mockEntityManager->expects($this->once())->method('flush');
        $this->mockLogger->expects($this->once())->method('info');

        $operation = $this->service->startOperation($account, $strategy, $threshold, $batchSize, $isDryRun);

        $this->assertTrue($operation->isDryRun());
        $this->assertEquals('running', $operation->getStatus());
    }

    /**
     * 测试完成操作记录.
     */
    public function testCompleteOperation(): void
    {
        $operation = new MergeOperation();
        $recordsCountBefore = 100;
        $recordsCountAfter = 80;
        $mergedRecordsCount = 20;
        $totalAmount = '150.00';
        $executionTime = '2.350';
        $resultMessage = 'Operation completed successfully';
        $context = ['test' => 'data'];

        $this->mockEntityManager->expects($this->once())
            ->method('persist')
            ->with($operation)
        ;

        $this->mockEntityManager->expects($this->once())
            ->method('flush')
        ;

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('完成合并操作记录', self::callback(fn ($v) => is_array($v)))
        ;

        $this->service->completeOperation(
            $operation,
            $recordsCountBefore,
            $recordsCountAfter,
            $mergedRecordsCount,
            $totalAmount,
            $executionTime,
            $resultMessage,
            $context
        );

        $this->assertEquals($recordsCountBefore, $operation->getRecordsCountBefore());
        $this->assertEquals($recordsCountAfter, $operation->getRecordsCountAfter());
        $this->assertEquals($mergedRecordsCount, $operation->getMergedRecordsCount());
        $this->assertEquals($totalAmount, $operation->getTotalAmount());
        $this->assertEquals('success', $operation->getStatus());
        $this->assertEquals($executionTime, $operation->getExecutionTime());
        $this->assertEquals($resultMessage, $operation->getResultMessage());
        $this->assertEquals($context, $operation->getContext());
    }

    /**
     * 测试完成操作记录（最小参数）.
     */
    public function testCompleteOperationWithMinimalParams(): void
    {
        $operation = new MergeOperation();
        $recordsCountBefore = 50;
        $recordsCountAfter = 40;
        $mergedRecordsCount = 10;
        $totalAmount = '75.00';

        $this->mockEntityManager->expects($this->once())->method('persist');
        $this->mockEntityManager->expects($this->once())->method('flush');
        $this->mockLogger->expects($this->once())->method('info');

        $this->service->completeOperation(
            $operation,
            $recordsCountBefore,
            $recordsCountAfter,
            $mergedRecordsCount,
            $totalAmount
        );

        $this->assertEquals('success', $operation->getStatus());
        $this->assertNull($operation->getExecutionTime());
        $this->assertNull($operation->getResultMessage());
        $this->assertNull($operation->getContext());
    }

    /**
     * 测试操作失败记录.
     */
    public function testFailOperation(): void
    {
        $operation = new MergeOperation();
        $errorMessage = 'Database connection failed';
        $executionTime = '1.250';

        $this->mockEntityManager->expects($this->once())
            ->method('persist')
            ->with($operation)
        ;

        $this->mockEntityManager->expects($this->once())
            ->method('flush')
        ;

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with('合并操作失败', self::callback(fn ($v) => is_array($v)))
        ;

        $this->service->failOperation($operation, $errorMessage, $executionTime);

        $this->assertEquals('failed', $operation->getStatus());
        $this->assertEquals($errorMessage, $operation->getResultMessage());
        $this->assertEquals($executionTime, $operation->getExecutionTime());
    }

    /**
     * 测试操作失败记录（最小参数）.
     */
    public function testFailOperationWithMinimalParams(): void
    {
        $operation = new MergeOperation();
        $errorMessage = 'Validation error';

        $this->mockEntityManager->expects($this->once())->method('persist');
        $this->mockEntityManager->expects($this->once())->method('flush');
        $this->mockLogger->expects($this->once())->method('error');

        $this->service->failOperation($operation, $errorMessage);

        $this->assertEquals('failed', $operation->getStatus());
        $this->assertEquals($errorMessage, $operation->getResultMessage());
        $this->assertNull($operation->getExecutionTime());
    }

    /**
     * 测试记录统计数据.
     */
    public function testRecordStatistics(): void
    {
        $account = new Account();
        $strategy = TimeWindowStrategy::MONTH;

        // 创建模拟的 SmallAmountStats
        $stats = $this->createMock(SmallAmountStats::class);
        $stats->method('getAccount')->willReturn($account);
        $stats->method('getThreshold')->willReturn(5.0);
        $stats->method('getCount')->willReturn(25);
        $stats->method('getTotal')->willReturn(125.75);
        $stats->method('hasMergeableRecords')->willReturn(true);
        $stats->method('getPotentialRecordReduction')->willReturn(15);
        $stats->method('getMergeEfficiency')->willReturn(0.6);
        $stats->method('getAverageAmount')->willReturn(5.03);
        $stats->method('getGroupStats')->willReturn(['group1' => 10, 'group2' => 15]);

        $this->mockEntityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(MergeStatistics::class))
        ;

        $this->mockEntityManager->expects($this->once())
            ->method('flush')
        ;

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('记录统计数据', self::callback(fn ($v) => is_array($v)))
        ;

        $statistics = $this->service->recordStatistics($stats, $strategy);

        $this->assertInstanceOf(MergeStatistics::class, $statistics);
        $this->assertSame($account, $statistics->getAccount());
        $this->assertSame($strategy, $statistics->getTimeWindowStrategy());
        $this->assertEquals('5', $statistics->getMinAmountThreshold());
        $this->assertEquals(25, $statistics->getTotalSmallRecords());
        $this->assertEquals('125.75', $statistics->getTotalSmallAmount());
        $this->assertEquals(25, $statistics->getMergeableRecords());
        $this->assertEquals(15, $statistics->getPotentialRecordReduction());
        $this->assertEquals('0.60', $statistics->getMergeEfficiency());
        $this->assertEquals('5.03', $statistics->getAverageAmount());
        $this->assertEquals(2, $statistics->getTimeWindowGroups());
        $this->assertEquals(['group1' => 10, 'group2' => 15], $statistics->getGroupStats());
    }

    /**
     * 测试记录统计数据（无可合并记录）.
     */
    public function testRecordStatisticsWithNoMergeableRecords(): void
    {
        $account = new Account();
        $strategy = TimeWindowStrategy::DAY;

        $stats = $this->createMock(SmallAmountStats::class);
        $stats->method('getAccount')->willReturn($account);
        $stats->method('getThreshold')->willReturn(10.0);
        $stats->method('getCount')->willReturn(5);
        $stats->method('getTotal')->willReturn(45.0);
        $stats->method('hasMergeableRecords')->willReturn(false);
        $stats->method('getPotentialRecordReduction')->willReturn(0);
        $stats->method('getMergeEfficiency')->willReturn(0.0);
        $stats->method('getAverageAmount')->willReturn(9.0);
        $stats->method('getGroupStats')->willReturn([]);

        $this->mockEntityManager->expects($this->once())->method('persist');
        $this->mockEntityManager->expects($this->once())->method('flush');
        $this->mockLogger->expects($this->once())->method('info');

        $statistics = $this->service->recordStatistics($stats, $strategy);

        $this->assertEquals(0, $statistics->getMergeableRecords());
        $context = $statistics->getContext();
        $this->assertIsArray($context);
        $this->assertArrayHasKey('has_mergeable_records', $context);
        $this->assertFalse($context['has_mergeable_records']);
    }

    /**
     * 测试获取最近操作记录.
     */
    public function testGetLatestOperation(): void
    {
        $account = new Account();
        $expectedOperation = new MergeOperation();

        $this->mockMergeOperationRepository->expects($this->once())
            ->method('findLatestByAccount')
            ->with($account)
            ->willReturn($expectedOperation)
        ;

        $result = $this->service->getLatestOperation($account);

        $this->assertSame($expectedOperation, $result);
    }

    /**
     * 测试获取最近操作记录（无记录）.
     */
    public function testGetLatestOperationReturnsNull(): void
    {
        $account = new Account();

        $this->mockMergeOperationRepository->expects($this->once())
            ->method('findLatestByAccount')
            ->with($account)
            ->willReturn(null)
        ;

        $result = $this->service->getLatestOperation($account);

        $this->assertNull($result);
    }

    /**
     * 测试获取最新统计数据.
     */
    public function testGetLatestStatistics(): void
    {
        $account = new Account();
        $expectedStatistics = new MergeStatistics();

        $this->mockMergeStatisticsRepository->expects($this->once())
            ->method('findLatestByAccount')
            ->with($account)
            ->willReturn($expectedStatistics)
        ;

        $result = $this->service->getLatestStatistics($account);

        $this->assertSame($expectedStatistics, $result);
    }

    /**
     * 测试获取最新统计数据（无记录）.
     */
    public function testGetLatestStatisticsReturnsNull(): void
    {
        $account = new Account();

        $this->mockMergeStatisticsRepository->expects($this->once())
            ->method('findLatestByAccount')
            ->with($account)
            ->willReturn(null)
        ;

        $result = $this->service->getLatestStatistics($account);

        $this->assertNull($result);
    }

    /**
     * 测试获取操作统计汇总.
     */
    public function testGetOperationsSummary(): void
    {
        $expectedSummary = [
            'total_operations' => 50,
            'successful_operations' => 45,
            'failed_operations' => 5,
            'total_merged_records' => 1250,
        ];

        $this->mockMergeOperationRepository->expects($this->once())
            ->method('getSuccessfulOperationsStats')
            ->willReturn($expectedSummary)
        ;

        $result = $this->service->getOperationsSummary();

        $this->assertEquals($expectedSummary, $result);
    }

    /**
     * 测试获取全局统计汇总.
     */
    public function testGetGlobalStatsSummary(): void
    {
        $expectedSummary = [
            'total_statistics' => 100,
            'total_small_records' => 5000,
            'total_small_amount' => '25000.00',
            'average_merge_efficiency' => 0.65,
        ];

        $this->mockMergeStatisticsRepository->expects($this->once())
            ->method('getGlobalStatsSummary')
            ->willReturn($expectedSummary)
        ;

        $result = $this->service->getGlobalStatsSummary();

        $this->assertEquals($expectedSummary, $result);
    }

    /**
     * 测试不同时间窗口策略的开始操作.
     */
    #[DataProvider('timeWindowStrategyProvider')]
    public function testStartOperationWithDifferentStrategies(TimeWindowStrategy $strategy): void
    {
        $account = new Account();
        $threshold = '5.00';
        $batchSize = 100;

        $this->mockEntityManager->expects($this->once())->method('persist');
        $this->mockEntityManager->expects($this->once())->method('flush');
        $this->mockLogger->expects($this->once())->method('info');

        $operation = $this->service->startOperation($account, $strategy, $threshold, $batchSize);

        $this->assertSame($strategy, $operation->getTimeWindowStrategy());
        $this->assertEquals('running', $operation->getStatus());
    }

    /**
     * 数据提供者：时间窗口策略.
     *
     * @return array<string, array{TimeWindowStrategy}>
     */
    public static function timeWindowStrategyProvider(): array
    {
        return [
            'day' => [TimeWindowStrategy::DAY],
            'week' => [TimeWindowStrategy::WEEK],
            'month' => [TimeWindowStrategy::MONTH],
            'all' => [TimeWindowStrategy::ALL],
        ];
    }

    /**
     * 测试不同阈值的开始操作.
     */
    #[DataProvider('thresholdProvider')]
    public function testStartOperationWithDifferentThresholds(string $threshold): void
    {
        $account = new Account();
        $strategy = TimeWindowStrategy::DAY;
        $batchSize = 100;

        $this->mockEntityManager->expects($this->once())->method('persist');
        $this->mockEntityManager->expects($this->once())->method('flush');
        $this->mockLogger->expects($this->once())->method('info');

        $operation = $this->service->startOperation($account, $strategy, $threshold, $batchSize);

        $this->assertEquals($threshold, $operation->getMinAmountThreshold());
    }

    /**
     * 数据提供者：阈值金额.
     *
     * @return array<string, array{string}>
     */
    public static function thresholdProvider(): array
    {
        return [
            'small' => ['1.00'],
            'medium' => ['5.00'],
            'large' => ['10.00'],
            'very_large' => ['100.00'],
        ];
    }
}
