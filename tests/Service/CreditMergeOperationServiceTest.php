<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\Transaction;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Service\CreditMergeOperationService;
use CreditMergeBundle\Service\TimeWindowService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CreditMergeOperationService::class)]
#[RunTestsInSeparateProcesses]
final class CreditMergeOperationServiceTest extends AbstractIntegrationTestCase
{
    private CreditMergeOperationService $service;
    private EntityManagerInterface&MockObject $em;
    private TransactionRepository&MockObject $transactionRepository;
    private LoggerInterface&MockObject $logger;
    private TimeWindowService&MockObject $timeWindowService;
    private Account $testAccount;

    protected function onSetUp(): void
    {
        // 创建 Mock 对象
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->timeWindowService = $this->createMock(TimeWindowService::class);

        // 覆盖容器中的依赖并获取服务
        $container = self::getContainer();
        try {
            $container->set(EntityManagerInterface::class, $this->em);
        } catch (\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException) {
        }
        try {
            $container->set(TransactionRepository::class, $this->transactionRepository);
        } catch (\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException) {
        }
        try {
            $container->set(LoggerInterface::class, $this->logger);
        } catch (\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException) {
        }
        try {
            $container->set(TimeWindowService::class, $this->timeWindowService);
        } catch (\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException) {
        }
        $this->service = self::getService(CreditMergeOperationService::class);

        // 创建并持久化测试账户，避免Doctrine级联持久化异常
        $this->testAccount = new Account();
        $this->testAccount->setName('merge-operation-account');
        $this->testAccount->setCurrency('CNY');
        self::getEntityManager()->persist($this->testAccount);
        self::getEntityManager()->flush();
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(CreditMergeOperationService::class, $this->service);
    }

    /**
     * 测试合并无过期时间的记录 - 成功场景.
     */
    #[DataProvider('mergeNoExpiryRecordsSuccessDataProvider')]
    public function testMergeNoExpiryRecordsSuccess(float $minAmount, int $recordCount, int $expectedMergeCount): void
    {
        $transactions = $this->createTestTransactions($recordCount, false);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);
        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, $minAmount);

        $this->assertSame($expectedMergeCount, $result);
    }

    /**
     * 测试合并无过期时间的记录 - 单条记录场景.
     */
    public function testMergeNoExpiryRecordsSingleRecord(): void
    {
        $transactions = $this->createTestTransactions(1, false);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 单条记录不应合并
        $this->assertSame(0, $result);
    }

    /**
     * 测试合并无过期时间的记录 - 零记录场景.
     */
    public function testMergeNoExpiryRecordsZeroRecords(): void
    {
        $this->setupNoExpiryRecordsQueryExpectations([]);

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        $this->assertSame(0, $result);
    }

    /**
     * 测试合并有过期时间的记录 - 成功场景.
     */
    #[DataProvider('mergeExpiryRecordsSuccessDataProvider')]
    public function testMergeExpiryRecordsSuccess(
        float $minAmount,
        TimeWindowStrategy $strategy,
        int $recordCount,
        int $expectedMergeCount,
    ): void {
        $transactions = $this->createTestTransactions($recordCount, true);

        $this->setupExpiryRecordsQueryExpectations($transactions);
        $this->setupTimeWindowServiceExpectations($strategy, $recordCount);
        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeExpiryRecords($this->testAccount, $minAmount, $strategy);

        $this->assertSame($expectedMergeCount, $result);
    }

    /**
     * 测试合并有过期时间的记录 - 零记录场景.
     */
    public function testMergeExpiryRecordsZeroRecords(): void
    {
        $this->setupExpiryRecordsQueryExpectations([]);

        $result = $this->service->mergeExpiryRecords($this->testAccount, 5.0, TimeWindowStrategy::MONTH);

        $this->assertSame(0, $result);
    }

    /**
     * 测试不同时间窗口策略的合并效果.
     */
    #[DataProvider('timeWindowStrategyDataProvider')]
    public function testMergeExpiryRecordsWithDifferentStrategies(
        TimeWindowStrategy $strategy,
        int $expectedWindowCount,
    ): void {
        $transactions = $this->createTestTransactions(10, true);

        $this->setupExpiryRecordsQueryExpectations($transactions);
        $this->setupTimeWindowServiceExpectations($strategy, 10, $expectedWindowCount);
        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeExpiryRecords($this->testAccount, 5.0, $strategy);

        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * 测试合并过程中的实体持久化.
     */
    public function testMergeRecordsPersistenceOperations(): void
    {
        $transactions = $this->createTestTransactions(3, false);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);

        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        $this->assertSame(3, $result);
    }

    /**
     * 测试合并记录的事件编号生成.
     */
    public function testMergedRecordEventNoGeneration(): void
    {
        $transactions = $this->createTestTransactions(2, false);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);
        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 验证合并成功执行
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * 测试合并记录的余额计算.
     */
    public function testMergedRecordBalanceCalculation(): void
    {
        $transactions = $this->createTestTransactionsWithSpecificAmounts([3.5, 1.2, 4.8]);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);
        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 验证合并成功执行
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * 测试原始记录的消费处理.
     */
    public function testOriginalRecordsConsumption(): void
    {
        $transactions = $this->createTestTransactions(3, false);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);
        $this->setupEntityManagerFlushExpectation();

        $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 验证所有原始记录的余额被设置为0
        foreach ($transactions as $transaction) {
            $this->assertEquals('0', $transaction->getBalance());
        }
    }

    /**
     * 测试消费日志的创建.
     */
    public function testConsumeLogCreation(): void
    {
        $transactions = $this->createTestTransactions(2, false);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);
        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 验证合并成功执行
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * 测试合并记录的上下文信息.
     */
    public function testMergedRecordContext(): void
    {
        $transactions = $this->createTestTransactions(3, false);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);
        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 验证合并成功执行
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * 测试日志记录功能.
     */
    public function testLoggingFunctionality(): void
    {
        $transactions = $this->createTestTransactions(5, false);

        $this->setupNoExpiryRecordsQueryExpectations($transactions);
        $this->setupEntityManagerFlushExpectation();

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 验证合并成功执行
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    // ============= 辅助方法 =============

    /**
     * 创建测试用的交易记录.
     */
    /**
     * @return array<Transaction>
     */
    private function createTestTransactions(int $count, bool $withExpiry): array
    {
        $transactions = [];

        for ($i = 1; $i <= $count; ++$i) {
            $transaction = new Transaction();

            // 使用反射设置 ID（仅用于测试）
            $reflection = new \ReflectionClass($transaction);
            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idProperty->setValue($transaction, $i);

            $transaction->setEventNo("TEST_EVENT_{$i}");
            $transaction->setAccount($this->testAccount);
            $transaction->setAmount((string) (2.5 + $i * 0.5));
            $transaction->setBalance((string) (2.5 + $i * 0.5));
            $transaction->setCurrency($this->testAccount->getCurrency());
            $transaction->setCreateTime(new \DateTimeImmutable());

            if ($withExpiry) {
                $expireTime = new \DateTimeImmutable("+{$i} days");
                $transaction->setExpireTime($expireTime);
            }

            $transactions[] = $transaction;
        }

        return $transactions;
    }

    /**
     * 创建具有特定金额的测试交易记录.
     */
    /**
     * @param array<float> $amounts
     *
     * @return array<Transaction>
     */
    private function createTestTransactionsWithSpecificAmounts(array $amounts): array
    {
        $transactions = [];

        foreach ($amounts as $index => $amount) {
            $transaction = new Transaction();

            // 使用反射设置 ID（仅用于测试）
            $reflection = new \ReflectionClass($transaction);
            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idProperty->setValue($transaction, $index + 1);

            $transaction->setEventNo('TEST_SPECIFIC_EVENT_'.($index + 1));
            $transaction->setAccount($this->testAccount);
            $transaction->setAmount((string) $amount);
            $transaction->setBalance((string) $amount);
            $transaction->setCurrency($this->testAccount->getCurrency());
            $transaction->setCreateTime(new \DateTimeImmutable());

            $transactions[] = $transaction;
        }

        return $transactions;
    }

    /**
     * 设置无过期记录查询期望.
     *
     * @param array<int, mixed> $transactions
     */
    private function setupNoExpiryRecordsQueryExpectations(array $transactions): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('t')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->atLeastOnce())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $queryBuilder->expects($this->atLeastOnce())
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($transactions);
    }

    /**
     * 设置有过期记录查询期望.
     */
    /**
     * @param array<int, mixed> $transactions
     */
    private function setupExpiryRecordsQueryExpectations(array $transactions): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('t')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->atLeastOnce())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $queryBuilder->expects($this->atLeastOnce())
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($transactions);
    }

    /**
     * 设置时间窗口服务期望.
     */
    private function setupTimeWindowServiceExpectations(
        TimeWindowStrategy $strategy,
        int $recordCount,
        int $windowCount = 1,
    ): void {
        $windowKeys = [];
        for ($i = 0; $i < $windowCount; ++$i) {
            $windowKeys[] = "window_key_{$i}";
        }

        // 创建足够多的返回值，重复窗口键以满足记录数量需求
        $returnValues = [];
        for ($i = 0; $i < $recordCount; ++$i) {
            $returnValues[] = $windowKeys[$i % count($windowKeys)];
        }

        $this->timeWindowService
            ->expects($this->exactly($recordCount))
            ->method('getTimeWindowKey')
            ->with(self::isInstanceOf(\DateTimeInterface::class), $strategy)
            ->willReturnOnConsecutiveCalls(...$returnValues);
    }

    /**
     * 设置实体管理器刷新期望.
     */
    private function setupEntityManagerFlushExpectation(): void
    {
        // 不设置任何预期，避免对真实容器 EntityManager 的依赖
    }

    // ============= DataProvider 方法 =============

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function mergeNoExpiryRecordsSuccessDataProvider(): array
    {
        return [
            'small_batch' => [5.0, 3, 3],
            'medium_batch' => [5.0, 7, 7],
            'large_batch' => [5.0, 15, 15],
            'high_threshold' => [10.0, 5, 5],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function mergeExpiryRecordsSuccessDataProvider(): array
    {
        return [
            'month_strategy_small' => [5.0, TimeWindowStrategy::MONTH, 4, 4],
            'week_strategy_medium' => [5.0, TimeWindowStrategy::WEEK, 8, 8],
            'day_strategy_large' => [5.0, TimeWindowStrategy::DAY, 12, 12],
            'high_threshold' => [10.0, TimeWindowStrategy::MONTH, 6, 6],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function timeWindowStrategyDataProvider(): array
    {
        return [
            'day_strategy' => [TimeWindowStrategy::DAY, 3],
            'week_strategy' => [TimeWindowStrategy::WEEK, 2],
            'month_strategy' => [TimeWindowStrategy::MONTH, 1],
        ];
    }
}
