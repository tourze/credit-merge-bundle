<?php

declare(strict_types=1);

namespace CreditMergeBundle\Repository;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * MergeOperation Repository
 * 提供合并操作记录的查询方法.
 *
 * @extends ServiceEntityRepository<MergeOperation>
 */
#[AsRepository(entityClass: MergeOperation::class)]
class MergeOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MergeOperation::class);
    }

    /**
     * 保存实体到数据库.
     */
    public function save(MergeOperation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 从数据库删除实体.
     */
    public function remove(MergeOperation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据账户查找合并操作记录.
     *
     * @return array<MergeOperation>
     */
    public function findByAccount(Account $account): array
    {
        /** @var array<MergeOperation> $result */
        $result = $this->createQueryBuilder('mo')
            ->where('mo.account = :account')
            ->setParameter('account', $account)
            ->orderBy('mo.operationTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 根据状态查找操作记录.
     *
     * @return array<MergeOperation>
     */
    public function findByStatus(string $status): array
    {
        /** @var array<MergeOperation> $result */
        $result = $this->createQueryBuilder('mo')
            ->where('mo.status = :status')
            ->setParameter('status', $status)
            ->orderBy('mo.operationTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 查找指定时间范围内的操作记录.
     *
     * @return array<MergeOperation>
     */
    public function findByTimeRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        /** @var array<MergeOperation> $result */
        $result = $this->createQueryBuilder('mo')
            ->where('mo.operationTime BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('mo.operationTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 根据时间窗口策略查找操作记录.
     *
     * @return array<MergeOperation>
     */
    public function findByTimeWindowStrategy(TimeWindowStrategy $strategy): array
    {
        /** @var array<MergeOperation> $result */
        $result = $this->createQueryBuilder('mo')
            ->where('mo.timeWindowStrategy = :strategy')
            ->setParameter('strategy', $strategy)
            ->orderBy('mo.operationTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 获取成功的操作记录统计.
     *
     * @return array<string, mixed>
     */
    public function getSuccessfulOperationsStats(): array
    {
        /** @var array<string, mixed> */
        $result = $this->createQueryBuilder('mo')
            ->select([
                'COUNT(mo.id) as totalOperations',
                'SUM(mo.recordsCountBefore) as totalRecordsBefore',
                'SUM(mo.recordsCountAfter) as totalRecordsAfter',
                'SUM(mo.totalAmount) as totalAmount',
            ])
            ->where('mo.status = :status')
            ->setParameter('status', 'success')
            ->getQuery()
            ->getSingleResult()
        ;

        $totalRecordsBefore = isset($result['totalRecordsBefore']) && \is_numeric($result['totalRecordsBefore'])
            ? (int) $result['totalRecordsBefore'] : 0;
        $totalRecordsAfter = isset($result['totalRecordsAfter']) && \is_numeric($result['totalRecordsAfter'])
            ? (int) $result['totalRecordsAfter'] : 0;

        return [
            'total_operations' => isset($result['totalOperations']) && \is_numeric($result['totalOperations'])
                ? (int) $result['totalOperations'] : 0,
            'total_records_before' => $totalRecordsBefore,
            'total_records_after' => $totalRecordsAfter,
            'total_amount' => isset($result['totalAmount']) && (\is_string($result['totalAmount']) || \is_numeric($result['totalAmount']))
                ? (string) $result['totalAmount'] : '0.00',
            'records_reduction' => $totalRecordsBefore - $totalRecordsAfter,
        ];
    }

    /**
     * 获取账户的最近操作记录.
     */
    public function findLatestByAccount(Account $account): ?MergeOperation
    {
        /** @var MergeOperation|null $result */
        $result = $this->createQueryBuilder('mo')
            ->where('mo.account = :account')
            ->setParameter('account', $account)
            ->orderBy('mo.operationTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result;
    }

    /**
     * 根据多个条件查找操作记录.
     *
     * @param array<string, mixed> $criteria
     *
     * @return array<MergeOperation>
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('mo');

        if (isset($criteria['account'])) {
            $qb->andWhere('mo.account = :account')
                ->setParameter('account', $criteria['account'])
            ;
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('mo.status = :status')
                ->setParameter('status', $criteria['status'])
            ;
        }

        if (isset($criteria['isDryRun'])) {
            $qb->andWhere('mo.isDryRun = :isDryRun')
                ->setParameter('isDryRun', $criteria['isDryRun'])
            ;
        }

        if (isset($criteria['timeWindowStrategy'])) {
            $qb->andWhere('mo.timeWindowStrategy = :timeWindowStrategy')
                ->setParameter('timeWindowStrategy', $criteria['timeWindowStrategy'])
            ;
        }

        if (isset($criteria['fromDate'])) {
            $qb->andWhere('mo.operationTime >= :fromDate')
                ->setParameter('fromDate', $criteria['fromDate'])
            ;
        }

        if (isset($criteria['toDate'])) {
            $qb->andWhere('mo.operationTime <= :toDate')
                ->setParameter('toDate', $criteria['toDate'])
            ;
        }

        /** @var array<MergeOperation> $result */
        $result = $qb->orderBy('mo.operationTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }
}
