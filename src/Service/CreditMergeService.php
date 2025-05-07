<?php

namespace CreditMergeBundle\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 信用积分合并服务
 * 用于处理小额积分记录的合并逻辑
 */
class CreditMergeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly CreditMergeOperationService $operationService,
        private readonly CreditMergeStatsService $statsService,
    ) {
    }

    /**
     * 合并小额积分记录
     * 该方法将相同或相近过期时间的小额积分合并为一条记录，减少后续消费时的ConsumeLog数量
     *
     * @param Account $account 账户
     * @param float $minAmount 最小合并金额，低于此金额的记录将被合并
     * @param int $batchSize 每批处理的记录数
     * @param TimeWindowStrategy $timeWindowStrategy 时间窗口策略
     * @return int 合并的记录数量
     */
    public function mergeSmallAmounts(
        Account $account,
        float $minAmount = 5.0,
        int $batchSize = 100,
        TimeWindowStrategy $timeWindowStrategy = TimeWindowStrategy::MONTH
    ): int {
        $this->logger->info('开始合并小额积分', [
            'account_id' => $account->getId(),
            'min_amount' => $minAmount,
            'batch_size' => $batchSize,
            'strategy' => $timeWindowStrategy->value,
        ]);

        // 开启事务
        $this->entityManager->getConnection()->beginTransaction();

        try {
            $mergeCount = 0;

            // 1. 处理无过期时间的记录
            $mergeCount += $this->operationService->mergeNoExpiryRecords($account, $minAmount);

            // 2. 处理有过期时间的记录
            $mergeCount += $this->operationService->mergeExpiryRecords($account, $minAmount, $timeWindowStrategy);

            $this->entityManager->getConnection()->commit();

            $this->logger->info('积分合并完成', [
                'account_id' => $account->getId(),
                'merge_count' => $mergeCount,
                'strategy' => $timeWindowStrategy->value,
            ]);

            return $mergeCount;

        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();

            $this->logger->error('积分合并失败', [
                'account_id' => $account->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 获取账户小额积分统计信息
     *
     * @param Account $account 账户
     * @param float $threshold 小额阈值
     * @return SmallAmountStats 包含小额积分统计信息的对象
     */
    public function getSmallAmountStats(Account $account, float $threshold = 5.0): SmallAmountStats
    {
        return $this->statsService->getSmallAmountStats($account, $threshold);
    }

    /**
     * 获取带分组统计的小额积分详情
     *
     * @param Account $account 账户
     * @param float $threshold 小额阈值
     * @param TimeWindowStrategy $timeWindowStrategy 时间窗口策略
     * @return SmallAmountStats 包含详细统计信息的对象
     */
    public function getDetailedSmallAmountStats(
        Account $account,
        float $threshold = 5.0,
        TimeWindowStrategy $timeWindowStrategy = TimeWindowStrategy::MONTH
    ): SmallAmountStats {
        return $this->statsService->getDetailedSmallAmountStats($account, $threshold, $timeWindowStrategy);
    }
}
