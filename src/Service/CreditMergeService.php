<?php

namespace CreditMergeBundle\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

/**
 * 信用积分合并服务
 * 用于处理小额积分记录的合并逻辑.
 */
#[WithMonologChannel(channel: 'credit_merge')]
class CreditMergeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private CreditMergeOperationService $operationService,
        private CreditMergeStatsService $statsService,
        private MergeOperationRecordService $recordService,
    ) {
    }

    /**
     * 合并小额积分记录
     * 该方法将相同或相近过期时间的小额积分合并为一条记录，减少后续消费时的ConsumeLog数量.
     *
     * @param Account            $account            账户
     * @param float              $minAmount          最小合并金额，低于此金额的记录将被合并
     * @param int                $batchSize          每批处理的记录数
     * @param TimeWindowStrategy $timeWindowStrategy 时间窗口策略
     * @param bool               $isDryRun           是否为模拟运行
     *
     * @return int 合并的记录数量
     */
    public function mergeSmallAmounts(
        Account $account,
        float $minAmount = 5.0,
        int $batchSize = 100,
        TimeWindowStrategy $timeWindowStrategy = TimeWindowStrategy::MONTH,
        bool $isDryRun = false,
    ): int {
        $startTime = microtime(true);

        // 获取合并前的统计信息
        $statsBefore = $this->getDetailedSmallAmountStats($account, $minAmount, $timeWindowStrategy);
        $recordsCountBefore = $statsBefore->getCount();

        // 开始记录操作
        $operation = $this->recordService->startOperation(
            $account,
            $timeWindowStrategy,
            (string) $minAmount,
            $batchSize,
            $isDryRun
        );

        $this->logger->info('开始合并小额积分', [
            'operation_id' => $operation->getId(),
            'account_id' => $account->getId(),
            'min_amount' => $minAmount,
            'batch_size' => $batchSize,
            'strategy' => $timeWindowStrategy->value,
            'is_dry_run' => $isDryRun,
            'records_before' => $recordsCountBefore,
        ]);

        // 开启事务
        $this->entityManager->getConnection()->beginTransaction();

        try {
            $mergeCount = 0;

            if (!$isDryRun) {
                // 1. 处理无过期时间的记录
                $mergeCount += $this->operationService->mergeNoExpiryRecords($account, $minAmount);

                // 2. 处理有过期时间的记录
                $mergeCount += $this->operationService->mergeExpiryRecords($account, $minAmount, $timeWindowStrategy);
            }

            // 获取合并后的统计信息
            $statsAfter = $this->getDetailedSmallAmountStats($account, $minAmount, $timeWindowStrategy);
            $recordsCountAfter = $isDryRun ? $recordsCountBefore : $statsAfter->getCount();

            $this->entityManager->getConnection()->commit();

            $executionTime = number_format(microtime(true) - $startTime, 3);

            // 完成操作记录
            $this->recordService->completeOperation(
                $operation,
                $recordsCountBefore,
                $recordsCountAfter,
                $mergeCount,
                (string) $statsBefore->getTotal(),
                $executionTime,
                $isDryRun ? '模拟运行成功完成' : '合并操作成功完成',
                [
                    'records_reduction' => $recordsCountBefore - $recordsCountAfter,
                    'merge_efficiency' => $statsBefore->getMergeEfficiency(),
                    'average_amount' => $statsBefore->getAverageAmount(),
                ]
            );

            // 记录统计数据
            $this->recordService->recordStatistics($statsBefore, $timeWindowStrategy);

            $this->logger->info('积分合并完成', [
                'operation_id' => $operation->getId(),
                'account_id' => $account->getId(),
                'merge_count' => $mergeCount,
                'strategy' => $timeWindowStrategy->value,
                'execution_time' => $executionTime,
                'records_before' => $recordsCountBefore,
                'records_after' => $recordsCountAfter,
                'is_dry_run' => $isDryRun,
            ]);

            return $mergeCount;
        } catch (\Throwable $e) {
            $this->entityManager->getConnection()->rollBack();

            $executionTime = number_format(microtime(true) - $startTime, 3);

            // 标记操作失败
            $this->recordService->failOperation($operation, $e->getMessage(), $executionTime);

            $this->logger->error('积分合并失败', [
                'operation_id' => $operation->getId(),
                'account_id' => $account->getId(),
                'exception' => $e->getMessage(),
                'execution_time' => $executionTime,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 获取账户小额积分统计信息.
     *
     * @param Account $account   账户
     * @param float   $threshold 小额阈值
     *
     * @return SmallAmountStats 包含小额积分统计信息的对象
     */
    public function getSmallAmountStats(Account $account, float $threshold = 5.0): SmallAmountStats
    {
        return $this->statsService->getSmallAmountStats($account, $threshold);
    }

    /**
     * 获取带分组统计的小额积分详情.
     *
     * @param Account            $account            账户
     * @param float              $threshold          小额阈值
     * @param TimeWindowStrategy $timeWindowStrategy 时间窗口策略
     *
     * @return SmallAmountStats 包含详细统计信息的对象
     */
    public function getDetailedSmallAmountStats(
        Account $account,
        float $threshold = 5.0,
        TimeWindowStrategy $timeWindowStrategy = TimeWindowStrategy::MONTH,
    ): SmallAmountStats {
        return $this->statsService->getDetailedSmallAmountStats($account, $threshold, $timeWindowStrategy);
    }
}
