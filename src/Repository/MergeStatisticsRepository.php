<?php

declare(strict_types=1);

namespace CreditMergeBundle\Repository;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeStatistics;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * MergeStatistics Repository
 * 提供合并统计数据的查询方法.
 *
 * @extends ServiceEntityRepository<MergeStatistics>
 */
#[AsRepository(entityClass: MergeStatistics::class)]
class MergeStatisticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MergeStatistics::class);
    }

    /**
     * 保存实体到数据库.
     */
    public function save(MergeStatistics $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 从数据库删除实体.
     */
    public function remove(MergeStatistics $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据账户查找统计记录.
     *
     * @return array<MergeStatistics>
     */
    public function findByAccount(Account $account): array
    {
        /** @var array<MergeStatistics> $result */
        $result = $this->createQueryBuilder('ms')
            ->where('ms.account = :account')
            ->setParameter('account', $account)
            ->orderBy('ms.statisticsTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 根据时间窗口策略查找统计记录.
     *
     * @return array<MergeStatistics>
     */
    public function findByTimeWindowStrategy(TimeWindowStrategy $strategy): array
    {
        /** @var array<MergeStatistics> $result */
        $result = $this->createQueryBuilder('ms')
            ->where('ms.timeWindowStrategy = :strategy')
            ->setParameter('strategy', $strategy)
            ->orderBy('ms.statisticsTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 查找指定时间范围内的统计记录.
     *
     * @return array<MergeStatistics>
     */
    public function findByTimeRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        /** @var array<MergeStatistics> $result */
        $result = $this->createQueryBuilder('ms')
            ->where('ms.statisticsTime BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('ms.statisticsTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 获取账户的最新统计记录.
     */
    public function findLatestByAccount(Account $account): ?MergeStatistics
    {
        /** @var MergeStatistics|null $result */
        $result = $this->createQueryBuilder('ms')
            ->where('ms.account = :account')
            ->setParameter('account', $account)
            ->orderBy('ms.statisticsTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result;
    }

    /**
     * 获取指定账户和策略的最新统计记录.
     */
    public function findLatestByAccountAndStrategy(Account $account, TimeWindowStrategy $strategy): ?MergeStatistics
    {
        /** @var MergeStatistics|null $result */
        $result = $this->createQueryBuilder('ms')
            ->where('ms.account = :account')
            ->andWhere('ms.timeWindowStrategy = :strategy')
            ->setParameter('account', $account)
            ->setParameter('strategy', $strategy)
            ->orderBy('ms.statisticsTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result;
    }

    /**
     * 获取全局统计汇总.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStatsSummary(): array
    {
        /** @var array<string, mixed> */
        $result = $this->createQueryBuilder('ms')
            ->select([
                'COUNT(DISTINCT ms.account) as totalAccounts',
                'SUM(ms.totalSmallRecords) as totalSmallRecords',
                'SUM(ms.totalSmallAmount) as totalSmallAmount',
                'SUM(ms.mergeableRecords) as totalMergeableRecords',
                'SUM(ms.potentialRecordReduction) as totalPotentialReduction',
                'AVG(ms.mergeEfficiency) as averageMergeEfficiency',
            ])
            ->getQuery()
            ->getSingleResult()
        ;

        return $this->formatGlobalStatsResult($result);
    }

    /**
     * 格式化全局统计结果.
     *
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function formatGlobalStatsResult(array $result): array
    {
        return [
            'total_accounts' => $this->extractIntValue($result, 'totalAccounts'),
            'total_small_records' => $this->extractIntValue($result, 'totalSmallRecords'),
            'total_small_amount' => $this->extractStringValue($result, 'totalSmallAmount'),
            'total_mergeable_records' => $this->extractIntValue($result, 'totalMergeableRecords'),
            'total_potential_reduction' => $this->extractIntValue($result, 'totalPotentialReduction'),
            'average_merge_efficiency' => $this->extractFloatValue($result, 'averageMergeEfficiency'),
        ];
    }

    /**
     * 从结果中提取整数值.
     *
     * @param array<string, mixed> $result
     */
    private function extractIntValue(array $result, string $key): int
    {
        return isset($result[$key]) && \is_numeric($result[$key])
            ? (int) $result[$key] : 0;
    }

    /**
     * 从结果中提取字符串值.
     *
     * @param array<string, mixed> $result
     */
    private function extractStringValue(array $result, string $key): string
    {
        return isset($result[$key]) && (\is_string($result[$key]) || \is_numeric($result[$key]))
            ? (string) $result[$key] : '0.00';
    }

    /**
     * 从结果中提取浮点数值.
     *
     * @param array<string, mixed> $result
     */
    private function extractFloatValue(array $result, string $key): float
    {
        return isset($result[$key]) && \is_numeric($result[$key])
            ? (float) $result[$key] : 0.0;
    }

    /**
     * 按时间窗口策略分组统计.
     *
     * @return array<string, mixed>
     */
    public function getStatsByTimeWindowStrategy(): array
    {
        /** @var array<int, array<string, mixed>> */
        $results = $this->createQueryBuilder('ms')
            ->select([
                'ms.timeWindowStrategy',
                'COUNT(ms.id) as recordCount',
                'SUM(ms.totalSmallRecords) as totalSmallRecords',
                'SUM(ms.mergeableRecords) as totalMergeableRecords',
                'AVG(ms.mergeEfficiency) as averageEfficiency',
            ])
            ->groupBy('ms.timeWindowStrategy')
            ->getQuery()
            ->getResult()
        ;

        return $this->formatStrategyStatsResults($results);
    }

    /**
     * 格式化策略统计结果.
     *
     * @param array<int, array<string, mixed>> $results
     *
     * @return array<string, mixed>
     */
    private function formatStrategyStatsResults(array $results): array
    {
        $formatted = [];
        foreach ($results as $result) {
            $strategy = $this->extractTimeWindowStrategy($result);
            if (null === $strategy) {
                continue;
            }

            $formatted[$strategy->value] = $this->formatSingleStrategyResult($result, $strategy);
        }

        return $formatted;
    }

    /**
     * 从结果中提取时间窗口策略.
     *
     * @param array<string, mixed> $result
     */
    private function extractTimeWindowStrategy(array $result): ?TimeWindowStrategy
    {
        if (!isset($result['timeWindowStrategy']) || !($result['timeWindowStrategy'] instanceof TimeWindowStrategy)) {
            return null;
        }

        return $result['timeWindowStrategy'];
    }

    /**
     * 格式化单个策略统计结果.
     *
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function formatSingleStrategyResult(array $result, TimeWindowStrategy $strategy): array
    {
        return [
            'strategy' => $strategy,
            'record_count' => $this->extractIntValue($result, 'recordCount'),
            'total_small_records' => $this->extractIntValue($result, 'totalSmallRecords'),
            'total_mergeable_records' => $this->extractIntValue($result, 'totalMergeableRecords'),
            'average_efficiency' => $this->extractFloatValue($result, 'averageEfficiency'),
        ];
    }

    /**
     * 查找高效率合并的统计记录.
     *
     * @return array<MergeStatistics>
     */
    public function findHighEfficiencyStats(float $minEfficiency = 50.0): array
    {
        /** @var array<MergeStatistics> $result */
        $result = $this->createQueryBuilder('ms')
            ->where('ms.mergeEfficiency >= :minEfficiency')
            ->setParameter('minEfficiency', $minEfficiency)
            ->orderBy('ms.mergeEfficiency', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 根据多个条件查找统计记录.
     *
     * @param array<string, mixed> $criteria
     *
     * @return array<MergeStatistics>
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('ms');

        if (isset($criteria['account'])) {
            $qb->andWhere('ms.account = :account')
                ->setParameter('account', $criteria['account'])
            ;
        }

        if (isset($criteria['timeWindowStrategy'])) {
            $qb->andWhere('ms.timeWindowStrategy = :timeWindowStrategy')
                ->setParameter('timeWindowStrategy', $criteria['timeWindowStrategy'])
            ;
        }

        if (isset($criteria['minEfficiency'])) {
            $qb->andWhere('ms.mergeEfficiency >= :minEfficiency')
                ->setParameter('minEfficiency', $criteria['minEfficiency'])
            ;
        }

        if (isset($criteria['minSmallRecords'])) {
            $qb->andWhere('ms.totalSmallRecords >= :minSmallRecords')
                ->setParameter('minSmallRecords', $criteria['minSmallRecords'])
            ;
        }

        if (isset($criteria['fromDate'])) {
            $qb->andWhere('ms.statisticsTime >= :fromDate')
                ->setParameter('fromDate', $criteria['fromDate'])
            ;
        }

        if (isset($criteria['toDate'])) {
            $qb->andWhere('ms.statisticsTime <= :toDate')
                ->setParameter('toDate', $criteria['toDate'])
            ;
        }

        /** @var array<MergeStatistics> $result */
        $result = $qb->orderBy('ms.statisticsTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }
}
