<?php

declare(strict_types=1);

namespace CreditMergeBundle\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Entity\MergeStatistics;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Repository\MergeOperationRepository;
use CreditMergeBundle\Repository\MergeStatisticsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

/**
 * 合并操作记录服务
 * 负责记录合并操作的历史和统计数据.
 */
#[WithMonologChannel(channel: 'credit_merge_record')]
class MergeOperationRecordService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MergeOperationRepository $mergeOperationRepository,
        private MergeStatisticsRepository $mergeStatisticsRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 开始记录合并操作.
     */
    public function startOperation(
        Account $account,
        TimeWindowStrategy $timeWindowStrategy,
        string $minAmountThreshold,
        int $batchSize,
        bool $isDryRun = false,
    ): MergeOperation {
        $operation = new MergeOperation();
        $operation->setAccount($account);
        $operation->setTimeWindowStrategy($timeWindowStrategy);
        $operation->setMinAmountThreshold($minAmountThreshold);
        $operation->setBatchSize($batchSize);
        $operation->setIsDryRun($isDryRun);
        $operation->setStatus('running');
        $operation->setRecordsCountBefore(0);
        $operation->setRecordsCountAfter(0);
        $operation->setMergedRecordsCount(0);
        $operation->setTotalAmount('0.00');

        $this->entityManager->persist($operation);
        $this->entityManager->flush();

        $this->logger->info('开始记录合并操作', [
            'operation_id' => $operation->getId(),
            'account_id' => $account->getId(),
            'strategy' => $timeWindowStrategy->value,
            'is_dry_run' => $isDryRun,
        ]);

        return $operation;
    }

    /**
     * 完成合并操作记录.
     *
     * @param array<string, mixed>|null $context
     */
    public function completeOperation(
        MergeOperation $operation,
        int $recordsCountBefore,
        int $recordsCountAfter,
        int $mergedRecordsCount,
        string $totalAmount,
        ?string $executionTime = null,
        ?string $resultMessage = null,
        ?array $context = null,
    ): void {
        $operation->setRecordsCountBefore($recordsCountBefore);
        $operation->setRecordsCountAfter($recordsCountAfter);
        $operation->setMergedRecordsCount($mergedRecordsCount);
        $operation->setTotalAmount($totalAmount);
        $operation->setStatus('success');
        $operation->setExecutionTime($executionTime);
        $operation->setResultMessage($resultMessage);
        $operation->setContext($context);

        $this->entityManager->persist($operation);
        $this->entityManager->flush();

        $this->logger->info('完成合并操作记录', [
            'operation_id' => $operation->getId(),
            'records_before' => $recordsCountBefore,
            'records_after' => $recordsCountAfter,
            'merged_count' => $mergedRecordsCount,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * 标记操作失败.
     */
    public function failOperation(MergeOperation $operation, string $errorMessage, ?string $executionTime = null): void
    {
        $operation->setStatus('failed');
        $operation->setResultMessage($errorMessage);
        $operation->setExecutionTime($executionTime);

        $this->entityManager->persist($operation);
        $this->entityManager->flush();

        $this->logger->error('合并操作失败', [
            'operation_id' => $operation->getId(),
            'error' => $errorMessage,
        ]);
    }

    /**
     * 记录统计数据.
     */
    public function recordStatistics(SmallAmountStats $stats, TimeWindowStrategy $timeWindowStrategy): MergeStatistics
    {
        $statistics = new MergeStatistics();
        $statistics->setAccount($stats->getAccount());
        $statistics->setTimeWindowStrategy($timeWindowStrategy);
        $statistics->setMinAmountThreshold((string) $stats->getThreshold());
        $statistics->setTotalSmallRecords($stats->getCount());
        $statistics->setTotalSmallAmount((string) $stats->getTotal());
        $statistics->setMergeableRecords($stats->hasMergeableRecords() ? $stats->getCount() : 0);
        $statistics->setPotentialRecordReduction($stats->getPotentialRecordReduction());
        $statistics->setMergeEfficiency(number_format($stats->getMergeEfficiency(), 2));
        $statistics->setAverageAmount(number_format($stats->getAverageAmount(), 2));
        $statistics->setTimeWindowGroups(count($stats->getGroupStats()));
        $statistics->setGroupStats($stats->getGroupStats());
        $statistics->setContext([
            'has_mergeable_records' => $stats->hasMergeableRecords(),
            'strategy' => $timeWindowStrategy->value,
            'strategy_label' => $timeWindowStrategy->getLabel(),
        ]);

        $this->entityManager->persist($statistics);
        $this->entityManager->flush();

        $this->logger->info('记录统计数据', [
            'statistics_id' => $statistics->getId(),
            'account_id' => $stats->getAccount()->getId(),
            'total_records' => $stats->getCount(),
            'merge_efficiency' => $stats->getMergeEfficiency(),
        ]);

        return $statistics;
    }

    /**
     * 获取账户的最近操作记录.
     */
    public function getLatestOperation(Account $account): ?MergeOperation
    {
        return $this->mergeOperationRepository->findLatestByAccount($account);
    }

    /**
     * 获取账户的最新统计数据.
     */
    public function getLatestStatistics(Account $account): ?MergeStatistics
    {
        return $this->mergeStatisticsRepository->findLatestByAccount($account);
    }

    /**
     * 获取操作统计汇总.
     *
     * @return array<string, mixed>
     */
    public function getOperationsSummary(): array
    {
        return $this->mergeOperationRepository->getSuccessfulOperationsStats();
    }

    /**
     * 获取全局统计汇总.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStatsSummary(): array
    {
        return $this->mergeStatisticsRepository->getGlobalStatsSummary();
    }
}
