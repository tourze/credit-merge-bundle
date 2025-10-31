<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\Transaction;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Service\SmallAmountAnalysisService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * SmallAmountAnalysisService深度测试覆盖.
 *
 * 测试覆盖所有公共方法的核心业务逻辑，包括：
 * - 基础统计查询方法
 * - 记录查找与分组方法
 * - 高层业务组合方法
 * - 边界条件和异常情况处理
 *
 * @internal
 */
#[CoversClass(SmallAmountAnalysisService::class)]
#[RunTestsInSeparateProcesses]
final class SmallAmountAnalysisServiceTest extends AbstractIntegrationTestCase
{
    private SmallAmountAnalysisService $service;
    private Account $testAccount;
    private TransactionRepository $transactionRepository;

    protected function onSetUp(): void
    {
        $this->service = self::getService(SmallAmountAnalysisService::class);
        $this->transactionRepository = self::getService(TransactionRepository::class);

        // 创建测试账户
        $this->testAccount = new Account();
        $this->testAccount->setName('test-small-amount-account');
        $this->testAccount->setCurrency('CNY');

        self::getEntityManager()->persist($this->testAccount);
        self::getEntityManager()->flush();

        // 清理可能存在的测试数据
        $this->cleanupTestData();
    }

    protected function onTearDown(): void
    {
        $this->cleanupTestData();
        parent::onTearDown();
    }

    public function testRepositoryInstanceAvailable(): void
    {
        $count = $this->transactionRepository->count([]);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    private function cleanupTestData(): void
    {
        // 清理测试事务记录
        self::getEntityManager()->createQuery(
            'DELETE FROM CreditBundle\Entity\Transaction t WHERE t.account = :account'
        )->setParameter('account', $this->testAccount)
         ->execute();
    }

    /**
     * 测试基础统计查询 - fetchSmallAmountBasicStats.
     */
    public function testFetchSmallAmountBasicStats(): void
    {
        // 准备测试数据：创建不同金额的交易记录
        $this->createTestTransaction(3.5, null);  // 小额积分，无过期
        $this->createTestTransaction(2.0, new \DateTimeImmutable('+30 days')); // 小额积分，有过期
        $this->createTestTransaction(8.0, null);  // 超出阈值，应被忽略
        $this->createTestTransaction(1.5, null);  // 小额积分，无过期

        $threshold = 5.0;
        $result = $this->service->fetchSmallAmountBasicStats($this->testAccount, $threshold);

        // 验证统计结果
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(3, $result['count']); // 3条小额记录
        $actualTotal = isset($result['total']) && \is_numeric($result['total']) ? (float) $result['total'] : 0.0;
        $this->assertEquals(7.0, $actualTotal); // 3.5 + 2.0 + 1.5
    }

    /**
     * @param array<string, mixed> $input    输入参数：threshold, hasRecords
     * @param array<string, mixed> $expected 期望结果
     */
    #[DataProvider('basicStatsDataProvider')]
    public function testFetchSmallAmountBasicStatsWithDataProvider(array $input, array $expected): void
    {
        $threshold = \is_float($input['threshold']) ? $input['threshold']
            : (\is_numeric($input['threshold']) ? (float) $input['threshold'] : 5.0);

        if (\is_bool($input['hasRecords']) && $input['hasRecords']) {
            $this->createTestTransaction(2.5, null);
            $this->createTestTransaction(4.0, new \DateTimeImmutable('+15 days'));
        }

        $result = $this->service->fetchSmallAmountBasicStats($this->testAccount, $threshold);

        $this->assertEquals($expected['count'], $result['count']);
        $expectedTotal = \is_numeric($expected['total']) ? (float) $expected['total'] : 0.0;
        $actualTotal = isset($result['total']) && \is_numeric($result['total']) ? (float) $result['total'] : 0.0;
        $this->assertEquals($expectedTotal, $actualTotal);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function basicStatsDataProvider(): array
    {
        return [
            'empty_account' => [
                'input' => ['threshold' => 5.0, 'hasRecords' => false],
                'expected' => ['count' => 0, 'total' => 0.0],
            ],
            'with_records' => [
                'input' => ['threshold' => 5.0, 'hasRecords' => true],
                'expected' => ['count' => 2, 'total' => 6.5],
            ],
            'lower_threshold' => [
                'input' => ['threshold' => 3.0, 'hasRecords' => true],
                'expected' => ['count' => 1, 'total' => 2.5],
            ],
        ];
    }

    /**
     * 测试无过期时间统计查询 - fetchNoExpiryStats.
     */
    public function testFetchNoExpiryStats(): void
    {
        // 准备混合测试数据
        $this->createTestTransaction(2.5, null);  // 无过期，应计入
        $this->createTestTransaction(3.0, new \DateTimeImmutable('+10 days')); // 有过期，应忽略
        $this->createTestTransaction(1.5, null);  // 无过期，应计入
        $this->createTestTransaction(8.0, null);  // 超出阈值，应忽略

        $threshold = 5.0;
        $result = $this->service->fetchNoExpiryStats($this->testAccount, $threshold);

        $this->assertEquals(2, $result['count']); // 2条无过期记录
        $actualTotal = isset($result['total']) && \is_numeric($result['total']) ? (float) $result['total'] : 0.0;
        $this->assertEquals(4.0, $actualTotal); // 2.5 + 1.5
    }

    /**
     * 测试查找有过期时间记录 - findRecordsWithExpiryForStats.
     */
    public function testFindRecordsWithExpiryForStats(): void
    {
        $expireDate1 = new \DateTimeImmutable('+15 days');
        $expireDate2 = new \DateTimeImmutable('+30 days');

        // 准备测试数据
        $transaction1 = $this->createTestTransaction(2.5, $expireDate1);
        $transaction2 = $this->createTestTransaction(3.5, null); // 无过期，应忽略
        $transaction3 = $this->createTestTransaction(1.8, $expireDate2);

        $threshold = 5.0;
        $records = $this->service->findRecordsWithExpiryForStats($this->testAccount, $threshold);

        // 应返回2条有过期时间的记录
        $this->assertCount(2, $records);

        $recordIds = array_map(fn ($record) => $record->getId(), $records);
        $this->assertContains($transaction1->getId(), $recordIds);
        $this->assertContains($transaction3->getId(), $recordIds);
    }

    /**
     * 测试按时间窗口分组统计 - groupRecordsByTimeWindowForStats.
     */
    public function testGroupRecordsByTimeWindowForStats(): void
    {
        $expireDate1 = new \DateTimeImmutable('2024-01-15 10:00:00');
        $expireDate2 = new \DateTimeImmutable('2024-01-16 15:00:00');
        $expireDate3 = new \DateTimeImmutable('2024-01-15 20:00:00'); // 同一天

        // 创建带过期时间的记录
        $records = [
            $this->createTestTransaction(2.5, $expireDate1),
            $this->createTestTransaction(3.0, $expireDate2),
            $this->createTestTransaction(1.5, $expireDate3),
        ];

        // 按天分组
        $grouped = $this->service->groupRecordsByTimeWindowForStats(
            $records,
            TimeWindowStrategy::DAY
        );

        // 应该有2个日期组
        $this->assertCount(2, $grouped);

        // 验证第一组 (2024-01-15)
        $this->assertArrayHasKey('2024-01-15', $grouped);
        $this->assertEquals(2, $grouped['2024-01-15']['count']);
        $this->assertEquals(4.0, $grouped['2024-01-15']['total']); // 2.5 + 1.5

        // 验证第二组 (2024-01-16)
        $this->assertArrayHasKey('2024-01-16', $grouped);
        $this->assertEquals(1, $grouped['2024-01-16']['count']);
        $this->assertEquals(3.0, $grouped['2024-01-16']['total']);
    }

    /**
     * 测试不同时间窗口策略的分组.
     */
    #[DataProvider('timeWindowStrategyDataProvider')]
    public function testGroupRecordsByTimeWindowForStatsWithDifferentStrategies(
        TimeWindowStrategy $strategy,
        string $expectedKey,
    ): void {
        $expireDate = new \DateTimeImmutable('2024-01-15 10:00:00');
        $records = [$this->createTestTransaction(2.5, $expireDate)];

        $grouped = $this->service->groupRecordsByTimeWindowForStats($records, $strategy);

        $this->assertArrayHasKey($expectedKey, $grouped);
        $this->assertEquals(1, $grouped[$expectedKey]['count']);
        $this->assertEquals(2.5, $grouped[$expectedKey]['total']);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function timeWindowStrategyDataProvider(): array
    {
        return [
            'day_strategy' => [TimeWindowStrategy::DAY, '2024-01-15'],
            'week_strategy' => [TimeWindowStrategy::WEEK, '2024-W03'],
            'month_strategy' => [TimeWindowStrategy::MONTH, '2024-01'],
            'all_strategy' => [TimeWindowStrategy::ALL, 'all'],
        ];
    }

    /**
     * 测试添加无过期统计到结果 - addNoExpiryStatsToResult.
     */
    public function testAddNoExpiryStatsToResult(): void
    {
        // 准备无过期记录
        $this->createTestTransaction(2.5, null);
        $this->createTestTransaction(3.5, null);

        $stats = SmallAmountStats::createEmpty($this->testAccount, 5.0);
        $this->service->addNoExpiryStatsToResult($this->testAccount, 5.0, $stats);

        $groupStats = $stats->getGroupStats();
        $this->assertArrayHasKey('no_expiry', $groupStats);
        $this->assertEquals(2, $groupStats['no_expiry']['count']);
        $this->assertEquals(6.0, $groupStats['no_expiry']['total']);
    }

    /**
     * 测试添加有过期统计到结果 - addExpiryStatsToResult.
     */
    public function testAddExpiryStatsToResult(): void
    {
        $expireDate1 = new \DateTimeImmutable('2024-01-15 10:00:00');
        $expireDate2 = new \DateTimeImmutable('2024-01-16 15:00:00');

        // 准备有过期记录
        $this->createTestTransaction(2.5, $expireDate1);
        $this->createTestTransaction(3.5, $expireDate2);

        $stats = SmallAmountStats::createEmpty($this->testAccount, 5.0);
        $this->service->addExpiryStatsToResult(
            $this->testAccount,
            5.0,
            TimeWindowStrategy::DAY,
            $stats
        );

        $groupStats = $stats->getGroupStats();
        $this->assertCount(2, $groupStats); // 两个不同的日期组
    }

    /**
     * 测试查找无过期记录 - findNoExpiryRecords.
     */
    public function testFindNoExpiryRecords(): void
    {
        // 准备测试数据 (balance = amount 确保未被消费)
        $transaction1 = $this->createTestTransaction(2.5, null);
        $transaction2 = $this->createTestTransaction(3.0, new \DateTimeImmutable('+10 days')); // 有过期，忽略
        $transaction3 = $this->createTestTransaction(1.5, null);

        // 创建部分消费记录，应被忽略
        $partialTransaction = $this->createTestTransaction(4.0, null);
        $partialTransaction->setBalance('2.0'); // balance != amount
        self::getEntityManager()->flush();

        $records = $this->service->findNoExpiryRecords($this->testAccount, 5.0);

        $this->assertCount(2, $records); // 只有2条完整的无过期记录
        $recordIds = array_map(fn ($record) => $record->getId(), $records);
        $this->assertContains($transaction1->getId(), $recordIds);
        $this->assertContains($transaction3->getId(), $recordIds);
    }

    /**
     * 测试查找有过期记录 - findExpiryRecords.
     */
    public function testFindExpiryRecords(): void
    {
        $expireDate1 = new \DateTimeImmutable('+15 days');
        $expireDate2 = new \DateTimeImmutable('+5 days'); // 更早过期

        $transaction1 = $this->createTestTransaction(2.5, $expireDate1);
        $transaction2 = $this->createTestTransaction(3.0, null); // 无过期，忽略
        $transaction3 = $this->createTestTransaction(1.5, $expireDate2);

        $records = $this->service->findExpiryRecords($this->testAccount, 5.0);

        $this->assertCount(2, $records);
        // 验证按过期时间升序排列
        $this->assertEquals($transaction3->getId(), $records[0]->getId()); // 更早过期的在前
        $this->assertEquals($transaction1->getId(), $records[1]->getId());
    }

    /**
     * 测试多时间窗口分组 - groupRecordsByTimeWindows.
     */
    public function testGroupRecordsByTimeWindows(): void
    {
        $expireDate = new \DateTimeImmutable('2024-01-15 10:00:00');
        $records = [$this->createTestTransaction(2.5, $expireDate)];

        $grouped = $this->service->groupRecordsByTimeWindows($records);

        // 验证包含所有时间窗口类型
        $this->assertArrayHasKey('day', $grouped);
        $this->assertArrayHasKey('week', $grouped);
        $this->assertArrayHasKey('month', $grouped);

        // 验证每个组都有记录
        $this->assertCount(1, $grouped['day']);
        $this->assertCount(1, $grouped['week']);
        $this->assertCount(1, $grouped['month']);
    }

    /**
     * 测试单时间窗口分组 - groupByTimeWindow.
     */
    public function testGroupByTimeWindow(): void
    {
        $expireDate1 = new \DateTimeImmutable('2024-01-15 10:00:00');
        $expireDate2 = new \DateTimeImmutable('2024-01-15 20:00:00'); // 同一天

        $transaction1 = $this->createTestTransaction(2.5, $expireDate1);
        $transaction2 = $this->createTestTransaction(3.5, $expireDate2);
        $records = [$transaction1, $transaction2];

        $grouped = $this->service->groupByTimeWindow($records, TimeWindowStrategy::DAY);

        $this->assertCount(1, $grouped); // 一个日期组
        $group = $grouped[0];

        $this->assertEquals('2024-01-15', $group['window_key']);
        $this->assertEquals(2, $group['count']);
        $this->assertEquals(6.0, $group['total_amount']);
        $this->assertCount(2, \is_countable($group['records']) ? $group['records'] : []);
        $this->assertEquals($expireDate1, $group['min_expire']);
        $this->assertEquals($expireDate2, $group['max_expire']);
    }

    /**
     * 测试计算总金额方法.
     */
    public function testCalculateTotalAmount(): void
    {
        // 准备测试记录
        $transaction1 = $this->createTestTransaction(2.5, null);
        $transaction2 = $this->createTestTransaction(3.0, null);
        $transaction3 = $this->createTestTransaction(1.5, null);

        $records = [$transaction1, $transaction2, $transaction3];

        // 测试计算总金额
        $totalAmount = $this->service->calculateTotalAmount($records);

        $this->assertEquals(7.0, $totalAmount);
    }

    /**
     * 测试计算总金额 - 空数组.
     */
    public function testCalculateTotalAmountWithEmptyArray(): void
    {
        $totalAmount = $this->service->calculateTotalAmount([]);

        $this->assertEquals(0.0, $totalAmount);
    }

    /**
     * 测试空记录处理.
     */
    public function testEmptyRecordsHandling(): void
    {
        // 测试各种方法对空记录的处理
        $emptyRecords = [];

        $grouped = $this->service->groupRecordsByTimeWindowForStats($emptyRecords, TimeWindowStrategy::DAY);
        $this->assertEmpty($grouped);

        $grouped = $this->service->groupByTimeWindow($emptyRecords, TimeWindowStrategy::DAY);
        $this->assertEmpty($grouped);

        $grouped = $this->service->groupRecordsByTimeWindows($emptyRecords);
        $this->assertEmpty($grouped['day']);
        $this->assertEmpty($grouped['week']);
        $this->assertEmpty($grouped['month']);
    }

    /**
     * 创建测试交易记录的辅助方法.
     */
    private function createTestTransaction(
        float $amount,
        ?\DateTimeInterface $expireTime = null,
    ): Transaction {
        $transaction = new Transaction();
        $transaction->setAccount($this->testAccount);
        $transaction->setAmount((string) $amount);
        $transaction->setBalance((string) $amount); // 默认未消费
        $transaction->setExpireTime($expireTime);
        $transaction->setCreateTime(new \DateTimeImmutable());

        // 设置必需的字段
        $transaction->setEventNo('test-event-'.uniqid());
        $transaction->setCurrency($this->testAccount->getCurrency());

        self::getEntityManager()->persist($transaction);
        self::getEntityManager()->flush();

        return $transaction;
    }
}
