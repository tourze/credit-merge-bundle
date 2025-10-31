<?php

declare(strict_types=1);

namespace CreditMergeBundle\DataFixtures;

use CreditBundle\Entity\Account;
use CreditBundle\Repository\AccountRepository;
use CreditMergeBundle\Entity\MergeStatistics;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * 合并统计历史数据填充.
 *
 * 创建测试用的合并统计数据
 * 只在 test 和 dev 环境中加载
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class MergeStatisticsFixtures extends Fixture implements FixtureGroupInterface
{
    public const MERGE_STATISTICS_REFERENCE_PREFIX = 'merge-statistics-';
    public const MERGE_STATISTICS_COUNT = 15;

    public function __construct(
        private readonly AccountRepository $accountRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 获取或创建测试账户
        $account = $this->getOrCreateTestAccount($manager);

        // 创建合并统计记录
        for ($i = 0; $i < self::MERGE_STATISTICS_COUNT; ++$i) {
            $mergeStatistics = new MergeStatistics();
            $mergeStatistics->setAccount($account);

            // 随机选择时间窗口策略
            $strategies = TimeWindowStrategy::cases();
            $strategy = $strategies[array_rand($strategies)];
            $mergeStatistics->setTimeWindowStrategy($strategy);

            // 设置最小金额阈值
            $mergeStatistics->setMinAmountThreshold((string) mt_rand(1, 10));

            // 设置小额记录总数量
            $totalSmallRecords = mt_rand(10, 200);
            $mergeStatistics->setTotalSmallRecords($totalSmallRecords);

            // 设置可合并的记录数量
            $mergeableRecords = mt_rand(5, $totalSmallRecords);
            $mergeStatistics->setMergeableRecords($mergeableRecords);

            // 设置潜在减少的记录数量
            $potentialReduction = (int) ($mergeableRecords * 0.8); // 假设80%可以被真正合并
            $mergeStatistics->setPotentialRecordReduction($potentialReduction);

            // 设置小额积分总额
            $totalSmallAmount = mt_rand(500, 50000) / 100;
            $mergeStatistics->setTotalSmallAmount((string) $totalSmallAmount);

            // 设置合并效率百分比
            $efficiency = mt_rand(60, 95);
            $mergeStatistics->setMergeEfficiency((string) $efficiency);

            // 设置平均每条记录金额
            $averageAmount = $totalSmallAmount / $totalSmallRecords;
            $mergeStatistics->setAverageAmount((string) $averageAmount);

            // 设置时间窗口分组数量
            $timeWindowGroups = match ($strategy) {
                TimeWindowStrategy::DAY => mt_rand(7, 30),
                TimeWindowStrategy::WEEK => mt_rand(4, 12),
                TimeWindowStrategy::MONTH => mt_rand(3, 6),
                TimeWindowStrategy::ALL => 1,
            };
            $mergeStatistics->setTimeWindowGroups($timeWindowGroups);

            // 设置分组统计详情
            $groupStats = [];
            for ($j = 0; $j < min($timeWindowGroups, 5); ++$j) { // 最多显示5个分组
                $groupKey = match ($strategy) {
                    TimeWindowStrategy::DAY => (new \DateTimeImmutable("-{$j} days"))->format('Y-m-d'),
                    TimeWindowStrategy::WEEK => (new \DateTimeImmutable("-{$j} weeks"))->format('Y-\WW'),
                    TimeWindowStrategy::MONTH => (new \DateTimeImmutable("-{$j} months"))->format('Y-m'),
                    TimeWindowStrategy::ALL => 'all',
                };
                $groupStats[$groupKey] = [
                    'record_count' => mt_rand(1, 20),
                    'total_amount' => mt_rand(100, 2000) / 100,
                    'average_amount' => mt_rand(50, 500) / 100,
                ];
            }
            $mergeStatistics->setGroupStats($groupStats);

            // 设置统计时间
            $daysAgo = mt_rand(1, 60);
            $statisticsTime = new \DateTimeImmutable("-{$daysAgo} days");
            $mergeStatistics->setStatisticsTime($statisticsTime);

            // 设置上下文信息
            $context = [
                'analysis_type' => 'periodic_merge_analysis',
                'data_range' => [
                    'start' => $statisticsTime->modify('-30 days')->format('Y-m-d'),
                    'end' => $statisticsTime->format('Y-m-d'),
                ],
                'threshold_settings' => [
                    'min_amount' => $mergeStatistics->getMinAmountThreshold(),
                    'strategy' => $strategy->value,
                ],
            ];
            $mergeStatistics->setContext($context);

            $manager->persist($mergeStatistics);
            $this->addReference(self::MERGE_STATISTICS_REFERENCE_PREFIX.$i, $mergeStatistics);
        }

        $manager->flush();
    }

    private function getOrCreateTestAccount(ObjectManager $manager): Account
    {
        // 尝试查找现有测试账户
        $account = $this->accountRepository->findOneBy(['name' => 'test-credit-merge']);

        if (null === $account) {
            // 创建新的测试账户
            $account = new Account();
            $account->setName('test-credit-merge');
            $account->setCurrency('CNY');
            $manager->persist($account);
            $manager->flush();
        }

        return $account;
    }

    public static function getGroups(): array
    {
        return [
            'credit-merge',
        ];
    }
}
