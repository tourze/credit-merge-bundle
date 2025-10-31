<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Service\CreditMergeOperationService;
use CreditMergeBundle\Service\CreditMergeService;
use CreditMergeBundle\Service\CreditMergeStatsService;
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
#[CoversClass(CreditMergeService::class)]
#[RunTestsInSeparateProcesses]
final class CreditMergeServiceTest extends AbstractIntegrationTestCase
{
    private CreditMergeService $service;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private CreditMergeOperationService&MockObject $operationService;
    private CreditMergeStatsService&MockObject $statsService;
    private MergeOperationRecordService&MockObject $recordService;
    private Account $testAccount;

    protected function onSetUp(): void
    {
        // 创建 Mock 对象
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->operationService = $this->createMock(CreditMergeOperationService::class);
        $this->statsService = $this->createMock(CreditMergeStatsService::class);
        $this->recordService = $this->createMock(MergeOperationRecordService::class);

        // 直接实例化服务并注入Mock依赖
        // 由于需要Mock EntityManager和Logger等核心服务，使用直接实例化
        /* @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $this->service = new CreditMergeService(
            $this->em,
            $this->logger,
            $this->operationService,
            $this->statsService,
            $this->recordService
        );

        // 创建测试用的账户
        $this->testAccount = new Account();
        // 注意：不能设置ID，因为它是由数据库自动生成的
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(CreditMergeService::class, $this->service);
    }

    /**
     * 测试成功的小额积分合并场景.
     */
    #[DataProvider('mergeSmallAmountsSuccessDataProvider')]
    public function testMergeSmallAmountsSuccess(
        float $minAmount,
        int $batchSize,
        TimeWindowStrategy $timeWindowStrategy,
        bool $isDryRun,
        int $expectedMergeCount,
    ): void {
        // 设置操作前后的统计数据
        $statsBefore = $this->createSmallAmountStats(100, 500.0);
        $statsAfter = $this->createSmallAmountStats(50, 500.0);

        // 模拟操作记录
        $mockOperation = $this->createMockOperation(1);

        // 设置 Mock 期望
        $this->setupSuccessfulMergeExpectations(
            $statsBefore,
            $statsAfter,
            $mockOperation,
            $expectedMergeCount,
            $isDryRun
        );

        // 执行测试
        $result = $this->service->mergeSmallAmounts(
            $this->testAccount,
            $minAmount,
            $batchSize,
            $timeWindowStrategy,
            $isDryRun
        );

        // 验证结果
        $this->assertSame($expectedMergeCount, $result);
    }

    /**
     * 测试小额积分合并异常场景.
     */
    public function testMergeSmallAmountsException(): void
    {
        $statsBefore = $this->createSmallAmountStats(100, 500.0);
        $mockOperation = $this->createMockOperation(1);
        $exceptionMessage = 'Database connection failed';
        $exception = new \RuntimeException($exceptionMessage);

        // 设置 Mock 期望
        $this->setupExceptionScenarioExpectations($statsBefore, $mockOperation, $exception);

        // 验证异常被抛出
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->service->mergeSmallAmounts($this->testAccount);
    }

    /**
     * 测试干运行模式.
     */
    public function testMergeSmallAmountsDryRun(): void
    {
        $statsBefore = $this->createSmallAmountStats(100, 500.0);
        $mockOperation = $this->createMockOperation(1);

        // 设置干运行期望
        $this->setupDryRunExpectations($statsBefore, $mockOperation);

        // 执行干运行
        $result = $this->service->mergeSmallAmounts(
            $this->testAccount,
            5.0,
            100,
            TimeWindowStrategy::MONTH,
            true
        );

        // 干运行应该返回0
        $this->assertSame(0, $result);
    }

    /**
     * 测试获取小额积分统计信息.
     */
    #[DataProvider('statsThresholdDataProvider')]
    public function testGetSmallAmountStats(float $threshold, int $expectedCount, float $expectedTotal): void
    {
        $expectedStats = $this->createSmallAmountStats($expectedCount, $expectedTotal);

        $this->statsService
            ->expects($this->once())
            ->method('getSmallAmountStats')
            ->with($this->testAccount, $threshold)
            ->willReturn($expectedStats);

        $result = $this->service->getSmallAmountStats($this->testAccount, $threshold);

        $this->assertSame($expectedStats, $result);
        $this->assertSame($expectedCount, $result->getCount());
        $this->assertSame($expectedTotal, $result->getTotal());
    }

    /**
     * 测试获取详细小额积分统计信息.
     */
    #[DataProvider('detailedStatsDataProvider')]
    public function testGetDetailedSmallAmountStats(
        float $threshold,
        TimeWindowStrategy $timeWindowStrategy,
        int $expectedCount,
        float $expectedTotal,
    ): void {
        $expectedStats = $this->createSmallAmountStats($expectedCount, $expectedTotal);

        $this->statsService
            ->expects($this->once())
            ->method('getDetailedSmallAmountStats')
            ->with($this->testAccount, $threshold, $timeWindowStrategy)
            ->willReturn($expectedStats);

        $result = $this->service->getDetailedSmallAmountStats(
            $this->testAccount,
            $threshold,
            $timeWindowStrategy
        );

        $this->assertSame($expectedStats, $result);
        $this->assertSame($expectedCount, $result->getCount());
        $this->assertSame($expectedTotal, $result->getTotal());
    }

    /**
     * 测试不同时间窗口策略.
     */
    #[DataProvider('timeWindowStrategyDataProvider')]
    public function testMergeSmallAmountsWithDifferentTimeWindowStrategies(
        TimeWindowStrategy $strategy,
        int $expectedMergeCount,
    ): void {
        $statsBefore = $this->createSmallAmountStats(50, 250.0);
        $statsAfter = $this->createSmallAmountStats(25, 250.0);
        $mockOperation = $this->createMockOperation(1);

        $this->setupSuccessfulMergeExpectations(
            $statsBefore,
            $statsAfter,
            $mockOperation,
            $expectedMergeCount,
            false
        );

        $result = $this->service->mergeSmallAmounts(
            $this->testAccount,
            5.0,
            100,
            $strategy,
            false
        );

        $this->assertSame($expectedMergeCount, $result);
    }

    /**
     * 测试边界条件：零记录合并.
     */
    public function testMergeSmallAmountsZeroRecords(): void
    {
        $statsBefore = $this->createSmallAmountStats(0, 0.0);
        $mockOperation = $this->createMockOperation(1);

        $this->setupZeroRecordsMergeExpectations($statsBefore, $mockOperation);

        $result = $this->service->mergeSmallAmounts($this->testAccount);

        $this->assertSame(0, $result);
    }

    /**
     * 创建小额积分统计对象
     */
    private function createSmallAmountStats(int $count, float $total): SmallAmountStats
    {
        return new SmallAmountStats($this->testAccount, $count, $total, 5.0);
    }

    /**
     * 创建模拟操作对象
     */
    private function createMockOperation(int $id): MergeOperation
    {
        // 对于当前测试场景，不依赖 ID 值，直接返回实体实例即可
        return new MergeOperation();
    }

    /**
     * 设置成功合并的期望.
     */
    private function setupSuccessfulMergeExpectations(
        SmallAmountStats $statsBefore,
        SmallAmountStats $statsAfter,
        MergeOperation $mockOperation,
        int $expectedMergeCount,
        bool $isDryRun,
    ): void {
        // 统计服务期望
        $this->statsService
            ->expects($this->exactly(2))
            ->method('getDetailedSmallAmountStats')
            ->willReturnOnConsecutiveCalls($statsBefore, $statsAfter);

        // 记录服务期望
        $this->recordService
            ->expects($this->once())
            ->method('startOperation')
            ->willReturn($mockOperation);

        $this->recordService
            ->expects($this->once())
            ->method('completeOperation');

        $this->recordService
            ->expects($this->once())
            ->method('recordStatistics');

        // EntityManager 期望
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');

        $this->em
            ->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($connection);

        if (!$isDryRun) {
            // 操作服务期望 - 正确分配合并计数，确保总数匹配
            $noExpiryCount = (int) ($expectedMergeCount / 2);
            $expiryCount = $expectedMergeCount - $noExpiryCount;

            $this->operationService
                ->expects($this->once())
                ->method('mergeNoExpiryRecords')
                ->willReturn($noExpiryCount);

            $this->operationService
                ->expects($this->once())
                ->method('mergeExpiryRecords')
                ->willReturn($expiryCount);
        }
    }

    /**
     * 设置异常场景期望.
     */
    private function setupExceptionScenarioExpectations(
        SmallAmountStats $statsBefore,
        MergeOperation $mockOperation,
        \Throwable $exception,
    ): void {
        $this->statsService
            ->expects($this->once())
            ->method('getDetailedSmallAmountStats')
            ->willReturn($statsBefore);

        $this->recordService
            ->expects($this->once())
            ->method('startOperation')
            ->willReturn($mockOperation);

        $this->recordService
            ->expects($this->once())
            ->method('failOperation');

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $this->em
            ->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($connection);

        $this->operationService
            ->expects($this->once())
            ->method('mergeNoExpiryRecords')
            ->willThrowException($exception);
    }

    /**
     * 设置干运行期望.
     */
    private function setupDryRunExpectations(
        SmallAmountStats $statsBefore,
        MergeOperation $mockOperation,
    ): void {
        $this->statsService
            ->expects($this->exactly(2))
            ->method('getDetailedSmallAmountStats')
            ->willReturn($statsBefore);

        $this->recordService
            ->expects($this->once())
            ->method('startOperation')
            ->willReturn($mockOperation);

        $this->recordService
            ->expects($this->once())
            ->method('completeOperation');

        $this->recordService
            ->expects($this->once())
            ->method('recordStatistics');

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');

        $this->em
            ->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($connection);

        // 干运行时不应调用操作服务
        $this->operationService->expects($this->never())->method('mergeNoExpiryRecords');
        $this->operationService->expects($this->never())->method('mergeExpiryRecords');
    }

    /**
     * 设置零记录合并期望.
     */
    private function setupZeroRecordsMergeExpectations(
        SmallAmountStats $statsBefore,
        MergeOperation $mockOperation,
    ): void {
        $this->statsService
            ->expects($this->exactly(2))
            ->method('getDetailedSmallAmountStats')
            ->willReturn($statsBefore);

        $this->recordService
            ->expects($this->once())
            ->method('startOperation')
            ->willReturn($mockOperation);

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');

        $this->em
            ->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($connection);
    }

    // ============= DataProvider 方法 =============

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function mergeSmallAmountsSuccessDataProvider(): array
    {
        return [
            'default_parameters' => [5.0, 100, TimeWindowStrategy::MONTH, false, 20],
            'high_threshold' => [10.0, 50, TimeWindowStrategy::WEEK, false, 15],
            'day_strategy' => [3.0, 200, TimeWindowStrategy::DAY, false, 25],
            'large_batch' => [5.0, 500, TimeWindowStrategy::MONTH, false, 30],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function statsThresholdDataProvider(): array
    {
        return [
            'default_threshold' => [5.0, 50, 250.0],
            'high_threshold' => [10.0, 20, 180.0],
            'low_threshold' => [1.0, 100, 450.0],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function detailedStatsDataProvider(): array
    {
        return [
            'month_strategy' => [5.0, TimeWindowStrategy::MONTH, 30, 150.0],
            'week_strategy' => [5.0, TimeWindowStrategy::WEEK, 45, 225.0],
            'day_strategy' => [5.0, TimeWindowStrategy::DAY, 60, 300.0],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function timeWindowStrategyDataProvider(): array
    {
        return [
            'month_strategy' => [TimeWindowStrategy::MONTH, 10],
            'week_strategy' => [TimeWindowStrategy::WEEK, 15],
            'day_strategy' => [TimeWindowStrategy::DAY, 20],
        ];
    }
}
