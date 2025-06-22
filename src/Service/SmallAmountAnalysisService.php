<?php

namespace CreditMergeBundle\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;

/**
 * 小额积分统计分析服务
 * 负责处理小额积分基础统计和分组逻辑
 */
class SmallAmountAnalysisService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly TimeWindowService $timeWindowService,
    ) {
    }

    /**
     * 获取小额积分基本统计数据
     */
    public function fetchSmallAmountBasicStats(Account $account, float $threshold): array 
    {
        return $this->transactionRepository
            ->createQueryBuilder('t')
            ->select('COUNT(t.id) as count, SUM(t.balance) as total')
            ->where('t.account = :account')
            ->andWhere('t.balance > 0 AND t.balance <= :threshold')
            ->setParameter('account', $account)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * 获取无过期时间记录的统计数据
     */
    public function fetchNoExpiryStats(Account $account, float $threshold): array 
    {
        return $this->transactionRepository
            ->createQueryBuilder('t')
            ->select('COUNT(t.id) as count, SUM(t.balance) as total')
            ->where('t.account = :account')
            ->andWhere('t.balance > 0 AND t.balance <= :threshold')
            ->andWhere('t.expireTime IS NULL')
            ->setParameter('account', $account)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * 向结果添加无过期时间记录的统计信息
     */
    public function addNoExpiryStatsToResult(Account $account, float $threshold, SmallAmountStats $stats): void 
    {
        $noExpiryStats = $this->fetchNoExpiryStats($account, $threshold);

        if ((int)($noExpiryStats['count'] ?? 0) > 0) {
            $stats->addGroupStats(
                'no_expiry',
                (int)($noExpiryStats['count'] ?? 0),
                (float)($noExpiryStats['total'] ?? 0)
            );
        }
    }

    /**
     * 查找用于统计的有过期时间记录
     */
    public function findRecordsWithExpiryForStats(Account $account, float $threshold): array 
    {
        return $this->transactionRepository
            ->createQueryBuilder('t')
            ->where('t.account = :account')
            ->andWhere('t.balance > 0 AND t.balance <= :threshold')
            ->andWhere('t.expireTime IS NOT NULL')
            ->setParameter('account', $account)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * 为统计目的按时间窗口分组记录
     */
    public function groupRecordsByTimeWindowForStats(array $records, TimeWindowStrategy $timeWindowStrategy): array 
    {
        $groupedByWindow = [];

        foreach ($records as $record) {
            $expireTime = $record->getExpireTime();
            if (!$expireTime) {
                continue;
            }

            $windowKey = $this->timeWindowService->getTimeWindowKey($expireTime, $timeWindowStrategy);
            if (!isset($groupedByWindow[$windowKey])) {
                $groupedByWindow[$windowKey] = [
                    'window' => $windowKey,
                    'count' => 0,
                    'total' => 0,
                    'earliestExpiry' => $expireTime
                ];
            }

            $groupedByWindow[$windowKey]['count']++;
            $groupedByWindow[$windowKey]['total'] += (float)$record->getBalance();

            // 保存最早的过期时间
            if ($expireTime < $groupedByWindow[$windowKey]['earliestExpiry']) {
                $groupedByWindow[$windowKey]['earliestExpiry'] = $expireTime;
            }
        }

        return $groupedByWindow;
    }

    /**
     * 添加分组统计到结果
     */
    public function addGroupStatsToResult(array $groupedByWindow, SmallAmountStats $stats): void 
    {
        foreach ($groupedByWindow as $windowKey => $groupData) {
            if ($groupData['count'] > 0) {
                $stats->addGroupStats(
                    $windowKey,
                    $groupData['count'],
                    $groupData['total'],
                    $groupData['earliestExpiry']
                );
            }
        }
    }

    /**
     * 向结果添加有过期时间记录的统计信息
     */
    public function addExpiryStatsToResult(
        Account $account,
        float $threshold,
        TimeWindowStrategy $timeWindowStrategy,
        SmallAmountStats $stats
    ): void {
        // 查询有过期时间的记录
        $recordsWithExpiry = $this->findRecordsWithExpiryForStats($account, $threshold);

        // 如果没有过期时间的记录，直接返回
        if (empty($recordsWithExpiry)) {
            return;
        }

        // 按时间窗口分组统计
        $groupedByWindow = $this->groupRecordsByTimeWindowForStats($recordsWithExpiry, $timeWindowStrategy);

        // 添加分组统计到结果中
        $this->addGroupStatsToResult($groupedByWindow, $stats);
    }

    /**
     * 查找无过期时间的小额积分记录
     */
    public function findNoExpiryRecords(Account $account, float $minAmount): array
    {
        return $this->transactionRepository
            ->createQueryBuilder('t')
            ->where('t.account = :account')
            ->andWhere('t.balance > 0 AND t.balance <= :minAmount')
            ->andWhere('t.balance = t.amount') // 只分析未被部分消费的记录
            ->andWhere('t.expireTime IS NULL')
            ->setParameter('account', $account)
            ->setParameter('minAmount', $minAmount)
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找有过期时间的小额积分记录
     */
    public function findExpiryRecords(Account $account, float $minAmount): array
    {
        return $this->transactionRepository
            ->createQueryBuilder('t')
            ->where('t.account = :account')
            ->andWhere('t.balance > 0 AND t.balance <= :minAmount')
            ->andWhere('t.balance = t.amount') // 只分析未被部分消费的记录
            ->andWhere('t.expireTime IS NOT NULL')
            ->setParameter('account', $account)
            ->setParameter('minAmount', $minAmount)
            ->orderBy('t.expireTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 计算总金额
     */
    public function calculateTotalAmount(array $records): float
    {
        $total = 0;
        foreach ($records as $record) {
            $total += floatval($record->getBalance());
        }
        return $total;
    }

    /**
     * 根据不同的时间窗口策略对记录进行分组
     */
    public function groupRecordsByTimeWindows(array $records): array
    {
        $byWindow = [
            'day' => $this->groupByTimeWindow($records, TimeWindowStrategy::DAY),
            'week' => $this->groupByTimeWindow($records, TimeWindowStrategy::WEEK),
            'month' => $this->groupByTimeWindow($records, TimeWindowStrategy::MONTH),
        ];

        return $byWindow;
    }

    /**
     * 根据时间窗口策略对记录进行分组
     */
    public function groupByTimeWindow(array $records, TimeWindowStrategy $strategy): array
    {
        $groupedByWindow = [];

        foreach ($records as $record) {
            $expireTime = $record->getExpireTime();
            if (!$expireTime) {
                continue;
            }

            $windowKey = $this->timeWindowService->getTimeWindowKey($expireTime, $strategy);

            if (!isset($groupedByWindow[$windowKey])) {
                $groupedByWindow[$windowKey] = [
                    'window_key' => $windowKey,
                    'records' => [],
                    'count' => 0,
                    'total_amount' => 0,
                    'min_expire' => $expireTime,
                    'max_expire' => $expireTime,
                ];
            }

            $groupedByWindow[$windowKey]['records'][] = $record;
            $groupedByWindow[$windowKey]['count']++;
            $groupedByWindow[$windowKey]['total_amount'] += floatval($record->getBalance());

            // 更新最早和最晚过期时间
            if ($expireTime < $groupedByWindow[$windowKey]['min_expire']) {
                $groupedByWindow[$windowKey]['min_expire'] = $expireTime;
            }
            if ($expireTime > $groupedByWindow[$windowKey]['max_expire']) {
                $groupedByWindow[$windowKey]['max_expire'] = $expireTime;
            }
        }

        return array_values($groupedByWindow);
    }
}
