<?php

namespace CreditMergeBundle\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\ConsumeLog;
use CreditBundle\Entity\Transaction;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 积分合并操作服务
 * 负责执行小额积分的合并操作
 */
class CreditMergeOperationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TransactionRepository $transactionRepository,
        private readonly LoggerInterface $logger,
        private readonly TimeWindowService $timeWindowService,
    ) {
    }

    /**
     * 合并无过期时间的记录
     */
    public function mergeNoExpiryRecords(Account $account, float $minAmount): int
    {
        $noExpiryRecords = $this->findNoExpiryRecords($account, $minAmount);

        $this->logger->debug('找到无过期时间的小额积分', [
            'account_id' => $account->getId(),
            'count' => count($noExpiryRecords),
        ]);

        if (count($noExpiryRecords) > 1) {
            $noExpiryMergeCount = $this->mergeRecordGroup($noExpiryRecords, $account, 'no_expiry');

            $this->logger->info('合并了无过期时间的积分', [
                'account_id' => $account->getId(),
                'count' => $noExpiryMergeCount,
            ]);

            return $noExpiryMergeCount;
        }

        return 0;
    }

    /**
     * 合并有过期时间的记录
     */
    public function mergeExpiryRecords(Account $account, float $minAmount, TimeWindowStrategy $timeWindowStrategy): int
    {
        $recordsWithExpiry = $this->findRecordsWithExpiry($account, $minAmount);

        if (empty($recordsWithExpiry)) {
            return 0;
        }

        // 按时间窗口分组
        $groupedByWindow = $this->groupRecordsByTimeWindow($recordsWithExpiry, $timeWindowStrategy);

        // 合并每个时间窗口的记录
        return $this->mergeGroupedRecords($groupedByWindow, $account);
    }

    /**
     * 查找无过期时间的记录
     */
    private function findNoExpiryRecords(Account $account, float $minAmount): array
    {
        return $this->transactionRepository
            ->createQueryBuilder('t')
            ->where('t.account = :account')
            ->andWhere('t.balance > 0 AND t.balance <= :minAmount')
            ->andWhere('t.balance = t.amount') // 只合并未被部分消费的记录
            ->andWhere('t.expireTime IS NULL')
            ->setParameter('account', $account)
            ->setParameter('minAmount', $minAmount)
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找有过期时间的记录
     */
    private function findRecordsWithExpiry(Account $account, float $minAmount): array
    {
        $recordsWithExpiry = $this->transactionRepository
            ->createQueryBuilder('t')
            ->where('t.account = :account')
            ->andWhere('t.balance > 0 AND t.balance <= :minAmount')
            ->andWhere('t.balance = t.amount') // 只合并未被部分消费的记录
            ->andWhere('t.expireTime IS NOT NULL')
            ->setParameter('account', $account)
            ->setParameter('minAmount', $minAmount)
            ->orderBy('t.expireTime', 'ASC')
            ->getQuery()
            ->getResult();

        $this->logger->debug('找到有过期时间的小额积分', [
            'account_id' => $account->getId(),
            'count' => count($recordsWithExpiry),
        ]);

        return $recordsWithExpiry;
    }

    /**
     * 合并分组记录
     */
    private function mergeGroupedRecords(array $groupedByWindow, Account $account): int
    {
        $totalMergeCount = 0;

        foreach ($groupedByWindow as $window) {
            if (count($window['records']) > 1) {
                $windowMergeCount = $this->mergeRecordGroup(
                    $window['records'],
                    $account,
                    $window['window'],
                    $window['earliestExpiry']
                );

                $totalMergeCount += $windowMergeCount;

                $this->logger->debug('合并了时间窗口组积分', [
                    'account_id' => $account->getId(),
                    'window' => $window['window'],
                    'count' => $windowMergeCount,
                ]);
            }
        }

        return $totalMergeCount;
    }

    /**
     * 按时间窗口分组记录
     */
    private function groupRecordsByTimeWindow(array $records, TimeWindowStrategy $timeWindowStrategy): array
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
                    'records' => [],
                    'earliestExpiry' => $expireTime
                ];
            }

            $groupedByWindow[$windowKey]['records'][] = $record;

            // 保存最早的过期时间用于合并后的记录
            if ($expireTime < $groupedByWindow[$windowKey]['earliestExpiry']) {
                $groupedByWindow[$windowKey]['earliestExpiry'] = $expireTime;
            }
        }

        $this->logger->debug('按时间窗口分组结果', [
            'group_count' => count($groupedByWindow),
        ]);

        return $groupedByWindow;
    }

    /**
     * 合并一组记录
     */
    private function mergeRecordGroup(
        array $records,
        Account $account,
        string $windowKey,
        ?\DateTimeInterface $expireTime = null
    ): int {
        if (count($records) <= 1) {
            return 0;
        }

        // 创建一条新的合并记录
        $recordIds = [];
        $totalBalance = $this->calculateTotalBalance($records, $recordIds);
        $mergedRecord = $this->createMergedRecord($account, $windowKey, $totalBalance, $expireTime, $recordIds);

        // 将原小额记录标记为已消费并记录合并日志
        $this->consumeOriginalRecords($records, $mergedRecord);

        // 提交更改
        $this->entityManager->flush();

        return count($records);
    }

    /**
     * 计算总余额并收集记录ID
     */
    private function calculateTotalBalance(array $records, array &$recordIds): float
    {
        $totalBalance = 0;
        foreach ($records as $record) {
            $totalBalance += floatval($record->getBalance());
            $recordIds[] = $record->getId();
        }
        return $totalBalance;
    }

    /**
     * 创建合并记录
     */
    private function createMergedRecord(
        Account $account,
        string $windowKey,
        float $totalBalance,
        ?\DateTimeInterface $expireTime,
        array $recordIds
    ): Transaction {
        $mergedRecord = new Transaction();
        $mergedRecord->setAccount($account);
        $mergedRecord->setCurrency($account->getCurrency());
        $mergedRecord->setEventNo('MERGE_' . $account->getId() . '_' . $windowKey . '_' . time() . '_' . rand(1000, 9999));
        $mergedRecord->setAmount((string)$totalBalance);
        $mergedRecord->setBalance((string)$totalBalance);
        $mergedRecord->setExpireTime($expireTime);
        $mergedRecord->setRemark('合并' . count($recordIds) . '条小额积分 (' . $windowKey . ')');
        $mergedRecord->setContext([
            'merged_records' => $recordIds,
            'merge_strategy' => $windowKey,
            'merge_time' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);

        $this->entityManager->persist($mergedRecord);

        return $mergedRecord;
    }

    /**
     * 消费原始记录
     */
    private function consumeOriginalRecords(array $records, Transaction $mergedRecord): void
    {
        foreach ($records as $record) {
            $record->setBalance('0');
            $this->entityManager->persist($record);

            // 记录合并日志
            $this->createConsumeLog($record, $mergedRecord);
        }
    }

    /**
     * 创建消费日志
     */
    private function createConsumeLog(Transaction $record, Transaction $mergedRecord): void
    {
        $consumeLog = new ConsumeLog();
        $consumeLog->setCostTransaction($record);
        $consumeLog->setConsumeTransaction($mergedRecord);
        $consumeLog->setAmount($record->getAmount());
        $this->entityManager->persist($consumeLog);
    }
}
