<?php

namespace CreditMergeBundle\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;

/**
 * 积分合并统计服务
 * 负责协调小额积分统计分析和合并潜力分析的服务
 */
class CreditMergeStatsService
{
    public function __construct(
        private readonly SmallAmountAnalysisService $smallAmountAnalysisService,
        private readonly MergePotentialAnalysisService $mergePotentialAnalysisService,
    ) {
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
        $stats = $this->smallAmountAnalysisService->fetchSmallAmountBasicStats($account, $threshold);

        return new SmallAmountStats(
            $account,
            (int)($stats['count'] ?? 0),
            (float)($stats['total'] ?? 0),
            $threshold
        );
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
        // 1. 获取基础统计信息
        $stats = $this->getSmallAmountStats($account, $threshold);
        $stats->setStrategy($timeWindowStrategy);

        // 如果没有记录，直接返回
        if ($stats->getCount() <= 0) {
            return $stats;
        }

        // 2. 添加无过期时间记录的分组统计
        $this->smallAmountAnalysisService->addNoExpiryStatsToResult($account, $threshold, $stats);

        // 3. 添加有过期时间记录的分组统计
        $this->smallAmountAnalysisService->addExpiryStatsToResult($account, $threshold, $timeWindowStrategy, $stats);

        return $stats;
    }

    /**
     * 获取账户的小额积分分布情况
     */
    public function getSmallAmountDistribution(Account $account, float $minAmount): array
    {
        $stats = [
            'total_count' => 0,
            'total_amount' => 0,
            'no_expiry' => [
                'count' => 0,
                'amount' => 0,
            ],
            'with_expiry' => [
                'count' => 0,
                'amount' => 0,
                'by_window' => [
                    'day' => [],
                    'week' => [],
                    'month' => [],
                ],
            ],
        ];

        // 1. 查询无过期时间的小额积分
        $noExpiryRecords = $this->smallAmountAnalysisService->findNoExpiryRecords($account, $minAmount);
        $stats['no_expiry']['count'] = count($noExpiryRecords);
        $stats['no_expiry']['amount'] = $this->smallAmountAnalysisService->calculateTotalAmount($noExpiryRecords);

        // 2. 查询有过期时间的小额积分
        $expiryRecords = $this->smallAmountAnalysisService->findExpiryRecords($account, $minAmount);
        $stats['with_expiry']['count'] = count($expiryRecords);
        $stats['with_expiry']['amount'] = $this->smallAmountAnalysisService->calculateTotalAmount($expiryRecords);

        // 3. 根据不同时间窗口分组有过期时间的记录
        $groupedByTimeWindow = $this->smallAmountAnalysisService->groupRecordsByTimeWindows($expiryRecords);

        // 4. 添加合并潜力分析
        $stats['with_expiry']['by_window'] = $this->mergePotentialAnalysisService->addMergePotentialToGroups($groupedByTimeWindow);

        // 5. 计算总计
        $stats['total_count'] = $stats['no_expiry']['count'] + $stats['with_expiry']['count'];
        $stats['total_amount'] = $stats['no_expiry']['amount'] + $stats['with_expiry']['amount'];
        $stats['merge_potential'] = $this->mergePotentialAnalysisService->calculateMergePotential($stats);

        return $stats;
    }
}
