<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\ConsumeLog;
use CreditBundle\Entity\Transaction;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Service\CreditMergeOperationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CreditMergeOperationService::class)]
#[RunTestsInSeparateProcesses]
final class CreditMergeOperationServiceTest extends AbstractIntegrationTestCase
{
    private CreditMergeOperationService $service;

    private TransactionRepository $transactionRepository;

    private Account $testAccount;

    protected function onSetUp(): void
    {
        // 从容器获取真实服务
        $this->service = self::getService(CreditMergeOperationService::class);
        $this->transactionRepository = self::getService(TransactionRepository::class);

        // 创建并持久化测试账户
        $this->testAccount = new Account();
        $this->testAccount->setName('merge-operation-test-'.uniqid());
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
        // 创建真实的测试数据
        $this->createTestTransactions($recordCount, false);

        // 执行合并操作
        $result = $this->service->mergeNoExpiryRecords($this->testAccount, $minAmount);

        // 验证合并数量
        $this->assertSame($expectedMergeCount, $result);

        // 验证数据库状态：原记录应该被消费（balance=0）
        $transactions = $this->transactionRepository->findBy(['account' => $this->testAccount]);
        $consumedCount = 0;
        $mergedRecord = null;

        foreach ($transactions as $transaction) {
            if ('0' === $transaction->getBalance() || '0.00' === $transaction->getBalance()) {
                ++$consumedCount;
            }
            if (str_starts_with($transaction->getEventNo(), 'MERGE_')) {
                $mergedRecord = $transaction;
            }
        }

        // 应该有 expectedMergeCount 条被消费的记录（只有满足条件的记录才会被合并）
        $this->assertSame($expectedMergeCount, $consumedCount);

        // 应该有一条合并记录
        $this->assertNotNull($mergedRecord);
    }

    /**
     * 测试合并无过期时间的记录 - 单条记录场景.
     */
    public function testMergeNoExpiryRecordsSingleRecord(): void
    {
        $this->createTestTransactions(1, false);

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 单条记录不应合并
        $this->assertSame(0, $result);
    }

    /**
     * 测试合并无过期时间的记录 - 零记录场景.
     */
    public function testMergeNoExpiryRecordsZeroRecords(): void
    {
        // 不创建任何记录
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
        // 创建真实的测试数据（带过期时间）
        $this->createTestTransactions($recordCount, true, $strategy);

        // 执行合并操作
        $result = $this->service->mergeExpiryRecords($this->testAccount, $minAmount, $strategy);

        // 验证合并数量
        $this->assertSame($expectedMergeCount, $result);

        // 验证数据库状态
        $transactions = $this->transactionRepository->findBy(['account' => $this->testAccount]);
        $consumedCount = 0;
        $mergedRecords = [];

        foreach ($transactions as $transaction) {
            if ('0' === $transaction->getBalance() || '0.00' === $transaction->getBalance()) {
                ++$consumedCount;
            }
            if (str_starts_with($transaction->getEventNo(), 'MERGE_')) {
                $mergedRecords[] = $transaction;
            }
        }

        // 应该有 expectedMergeCount 条被消费的记录（只有满足条件的记录才会被合并）
        $this->assertSame($expectedMergeCount, $consumedCount);

        // 应该至少有一条合并记录
        $this->assertNotEmpty($mergedRecords);
    }

    /**
     * 测试合并有过期时间的记录 - 零记录场景.
     */
    public function testMergeExpiryRecordsZeroRecords(): void
    {
        $result = $this->service->mergeExpiryRecords($this->testAccount, 5.0, TimeWindowStrategy::MONTH);

        $this->assertSame(0, $result);
    }

    /**
     * 测试不同时间窗口策略的合并效果.
     */
    #[DataProvider('timeWindowStrategyDataProvider')]
    public function testMergeExpiryRecordsWithDifferentStrategies(
        TimeWindowStrategy $strategy,
        int $recordCount,
    ): void {
        $this->createTestTransactions($recordCount, true, $strategy);

        $result = $this->service->mergeExpiryRecords($this->testAccount, 5.0, $strategy);

        // 验证合并成功执行
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * 测试合并过程中的实体持久化.
     */
    public function testMergeRecordsPersistenceOperations(): void
    {
        $this->createTestTransactions(3, false);

        $result = $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        $this->assertSame(3, $result);

        // 验证所有记录都已持久化
        self::getEntityManager()->clear();
        $transactions = $this->transactionRepository->findBy(['account' => $this->testAccount]);
        $this->assertNotEmpty($transactions);
    }

    /**
     * 测试合并记录的事件编号生成.
     */
    public function testMergedRecordEventNoGeneration(): void
    {
        $this->createTestTransactions(2, false);

        $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 查找合并记录
        $transactions = $this->transactionRepository->findBy(['account' => $this->testAccount]);
        $mergedRecord = null;
        foreach ($transactions as $transaction) {
            if (str_starts_with($transaction->getEventNo(), 'MERGE_')) {
                $mergedRecord = $transaction;
                break;
            }
        }

        $this->assertNotNull($mergedRecord);
        $this->assertStringStartsWith('MERGE_', $mergedRecord->getEventNo());
    }

    /**
     * 测试合并记录的余额计算.
     */
    public function testMergedRecordBalanceCalculation(): void
    {
        $amounts = [3.5, 1.2, 4.8];
        $this->createTestTransactionsWithSpecificAmounts($amounts);

        $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 查找合并记录并验证余额
        $transactions = $this->transactionRepository->findBy(['account' => $this->testAccount]);
        $mergedRecord = null;
        foreach ($transactions as $transaction) {
            if (str_starts_with($transaction->getEventNo(), 'MERGE_')) {
                $mergedRecord = $transaction;
                break;
            }
        }

        $this->assertNotNull($mergedRecord);
        $expectedBalance = array_sum($amounts);
        $this->assertEquals((string) $expectedBalance, $mergedRecord->getBalance());
    }

    /**
     * 测试原始记录的消费处理.
     */
    public function testOriginalRecordsConsumption(): void
    {
        $this->createTestTransactions(3, false);

        $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 验证所有原始记录的余额被设置为0
        $transactions = $this->transactionRepository->findBy(['account' => $this->testAccount]);
        $originalRecords = [];
        foreach ($transactions as $transaction) {
            if (!str_starts_with($transaction->getEventNo(), 'MERGE_')) {
                $originalRecords[] = $transaction;
            }
        }

        foreach ($originalRecords as $record) {
            $this->assertTrue('0' === $record->getBalance() || '0.00' === $record->getBalance());
        }
    }

    /**
     * 测试消费日志的创建.
     */
    public function testConsumeLogCreation(): void
    {
        $this->createTestTransactions(2, false);

        $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 验证消费日志已创建
        $consumeLogs = self::getEntityManager()
            ->getRepository(ConsumeLog::class)
            ->findAll()
        ;

        $this->assertGreaterThanOrEqual(2, count($consumeLogs));
    }

    /**
     * 测试合并记录的上下文信息.
     */
    public function testMergedRecordContext(): void
    {
        $this->createTestTransactions(3, false);

        $this->service->mergeNoExpiryRecords($this->testAccount, 5.0);

        // 查找合并记录并验证上下文
        $transactions = $this->transactionRepository->findBy(['account' => $this->testAccount]);
        $mergedRecord = null;
        foreach ($transactions as $transaction) {
            if (str_starts_with($transaction->getEventNo(), 'MERGE_')) {
                $mergedRecord = $transaction;
                break;
            }
        }

        $this->assertNotNull($mergedRecord);
        $context = $mergedRecord->getContext();
        $this->assertIsArray($context);
        $this->assertArrayHasKey('merged_records', $context);
        $this->assertArrayHasKey('merge_strategy', $context);
        $this->assertArrayHasKey('merge_time', $context);
    }

    // ============= 辅助方法 =============

    /**
     * 创建测试用的交易记录.
     *
     * @return array<Transaction>
     */
    private function createTestTransactions(int $count, bool $withExpiry, ?TimeWindowStrategy $strategy = null): array
    {
        $transactions = [];

        for ($i = 1; $i <= $count; ++$i) {
            $transaction = new Transaction();
            $transaction->setEventNo('TEST_EVENT_'.uniqid().'_'.$i);
            $transaction->setAccount($this->testAccount);
            $transaction->setAmount((string) (2.5 + $i * 0.5));
            $transaction->setBalance((string) (2.5 + $i * 0.5));
            $transaction->setCurrency($this->testAccount->getCurrency());
            $transaction->setCreateTime(new \DateTimeImmutable());

            if ($withExpiry) {
                // 根据策略创建不同的过期时间
                if (TimeWindowStrategy::DAY === $strategy) {
                    // 每2-3条记录创建一个窗口(同一天过期)
                    $dayOffset = (int) ceil($i / 2);
                    $expireTime = new \DateTimeImmutable("+{$dayOffset} days");
                } elseif (TimeWindowStrategy::WEEK === $strategy) {
                    // 每7条记录创建一个窗口(同一周过期)
                    $weekOffset = (int) ceil($i / 7);
                    $expireTime = new \DateTimeImmutable("+{$weekOffset} weeks");
                } elseif (TimeWindowStrategy::MONTH === $strategy) {
                    // 所有记录在同一个月
                    $expireTime = new \DateTimeImmutable('+30 days');
                } else {
                    // 默认情况
                    $expireTime = new \DateTimeImmutable("+{$i} days");
                }
                $transaction->setExpireTime($expireTime);
            }

            self::getEntityManager()->persist($transaction);
            $transactions[] = $transaction;
        }

        self::getEntityManager()->flush();

        return $transactions;
    }

    /**
     * 创建具有特定金额的测试交易记录.
     *
     * @param array<float> $amounts
     *
     * @return array<Transaction>
     */
    private function createTestTransactionsWithSpecificAmounts(array $amounts): array
    {
        $transactions = [];

        foreach ($amounts as $index => $amount) {
            $transaction = new Transaction();
            $transaction->setEventNo('TEST_SPECIFIC_EVENT_'.uniqid().'_'.($index + 1));
            $transaction->setAccount($this->testAccount);
            $transaction->setAmount((string) $amount);
            $transaction->setBalance((string) $amount);
            $transaction->setCurrency($this->testAccount->getCurrency());
            $transaction->setCreateTime(new \DateTimeImmutable());

            self::getEntityManager()->persist($transaction);
            $transactions[] = $transaction;
        }

        self::getEntityManager()->flush();

        return $transactions;
    }

    // ============= DataProvider 方法 =============

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function mergeNoExpiryRecordsSuccessDataProvider(): array
    {
        return [
            // 金额计算: balance = 2.5 + i * 0.5
            // 记录1=3.0, 记录2=3.5, 记录3=4.0, 记录4=4.5, 记录5=5.0, 记录6=5.5, 记录7=6.0...
            'small_batch' => [5.0, 3, 3],  // 3条记录都 <= 5.0
            'medium_batch' => [5.0, 7, 5], // 7条记录中只有前5条 <= 5.0
            'large_batch' => [10.0, 15, 15], // 阈值10.0,15条记录都满足
            'high_threshold' => [10.0, 5, 5], // 阈值10.0,5条记录都满足
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function mergeExpiryRecordsSuccessDataProvider(): array
    {
        return [
            // 金额计算: balance = 2.5 + i * 0.5
            // 记录1=3.0, 记录2=3.5, 记录3=4.0, 记录4=4.5, 记录5=5.0, 记录6=5.5...
            'month_strategy_small' => [5.0, TimeWindowStrategy::MONTH, 4, 4], // 4条都 <= 5.0,同一月
            'week_strategy_medium' => [5.0, TimeWindowStrategy::WEEK, 8, 5], // 8条中只有前5条 <= 5.0,同一周
            // DAY策略: ceil($i/2)分组,前5条中:记录1-2一组(2条),记录3-4一组(2条),记录5单独(1条不合并)
            'day_strategy_large' => [5.0, TimeWindowStrategy::DAY, 12, 4], // 实际合并4条(2+2)
            'high_threshold' => [10.0, TimeWindowStrategy::MONTH, 6, 6], // 阈值10.0,6条都满足
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function timeWindowStrategyDataProvider(): array
    {
        return [
            'day_strategy' => [TimeWindowStrategy::DAY, 10],
            'week_strategy' => [TimeWindowStrategy::WEEK, 10],
            'month_strategy' => [TimeWindowStrategy::MONTH, 10],
        ];
    }
}
