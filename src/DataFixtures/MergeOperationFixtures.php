<?php

declare(strict_types=1);

namespace CreditMergeBundle\DataFixtures;

use CreditBundle\Entity\Account;
use CreditBundle\Repository\AccountRepository;
use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * 合并操作记录数据填充.
 *
 * 创建测试用的合并操作记录数据
 * 只在 test 和 dev 环境中加载
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class MergeOperationFixtures extends Fixture implements FixtureGroupInterface
{
    public const MERGE_OPERATION_REFERENCE_PREFIX = 'merge-operation-';
    public const MERGE_OPERATION_COUNT = 10;

    public function __construct(
        private readonly AccountRepository $accountRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 获取或创建测试账户
        $account = $this->getOrCreateTestAccount($manager);

        // 创建合并操作记录
        for ($i = 0; $i < self::MERGE_OPERATION_COUNT; ++$i) {
            $mergeOperation = new MergeOperation();
            $mergeOperation->setAccount($account);

            // 随机选择时间窗口策略
            $strategies = TimeWindowStrategy::cases();
            $strategy = $strategies[array_rand($strategies)];
            $mergeOperation->setTimeWindowStrategy($strategy);

            // 设置最小合并金额阈值
            $mergeOperation->setMinAmountThreshold((string) mt_rand(1, 10));

            // 设置记录数量
            $recordsCountBefore = mt_rand(5, 100);
            $mergedRecordsCount = mt_rand(1, $recordsCountBefore);
            $recordsCountAfter = $recordsCountBefore - $mergedRecordsCount + 1; // +1 for the merged record

            $mergeOperation->setRecordsCountBefore($recordsCountBefore);
            $mergeOperation->setRecordsCountAfter($recordsCountAfter);
            $mergeOperation->setMergedRecordsCount($mergedRecordsCount);

            // 设置涉及的总金额
            $totalAmount = mt_rand(100, 10000) / 100;
            $mergeOperation->setTotalAmount((string) $totalAmount);

            // 设置批次大小
            $mergeOperation->setBatchSize(mt_rand(10, 100));

            // 设置是否为模拟运行 (20% 概率为模拟运行)
            $mergeOperation->setIsDryRun(mt_rand(1, 100) <= 20);

            // 设置状态
            $statuses = ['pending', 'running', 'success', 'failed', 'partial'];
            $status = $statuses[array_rand($statuses)];
            $mergeOperation->setStatus($status);

            // 设置操作时间
            $daysAgo = mt_rand(1, 30);
            $operationTime = new \DateTimeImmutable("-{$daysAgo} days");
            $mergeOperation->setOperationTime($operationTime);

            // 设置执行耗时 (仅成功状态有耗时)
            if (in_array($status, ['success', 'partial'], true)) {
                $executionTime = mt_rand(1, 300) / 1000; // 1-300ms
                $mergeOperation->setExecutionTime((string) $executionTime);
            }

            // 设置结果消息
            if ('failed' === $status) {
                $mergeOperation->setResultMessage('合并过程中发生错误：数据库连接超时');
            } elseif ('partial' === $status) {
                $mergeOperation->setResultMessage('部分记录合并成功，部分记录因余额不足跳过');
            }

            // 设置上下文信息
            $context = [
                'merge_reason' => '定期小额积分清理',
                'triggered_by' => 'system_cron_job',
                'performance_metrics' => [
                    'records_per_second' => round($mergedRecordsCount / ($executionTime ?? 1), 2),
                    'total_time_saved' => $mergedRecordsCount * 0.1, // 假设每条记录节省0.1秒查询时间
                ],
            ];
            $mergeOperation->setContext($context);

            $manager->persist($mergeOperation);
            $this->addReference(self::MERGE_OPERATION_REFERENCE_PREFIX.$i, $mergeOperation);
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
