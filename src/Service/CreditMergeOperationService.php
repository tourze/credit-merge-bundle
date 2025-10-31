<?php

namespace CreditMergeBundle\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\ConsumeLog;
use CreditBundle\Entity\Transaction;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

/**
 * 积分合并操作服务
 * 负责执行小额积分的合并操作.
 */
#[WithMonologChannel(channel: 'credit_merge')]
class CreditMergeOperationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TransactionRepository $transactionRepository,
        private LoggerInterface $logger,
        private TimeWindowService $timeWindowService,
    ) {
    }

    /**
     * 合并无过期时间的记录.
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
     * 合并有过期时间的记录.
     */
    public function mergeExpiryRecords(Account $account, float $minAmount, TimeWindowStrategy $timeWindowStrategy): int
    {
        $recordsWithExpiry = $this->findRecordsWithExpiry($account, $minAmount);

        if ([] === $recordsWithExpiry) {
            return 0;
        }

        // 按时间窗口分组
        $groupedByWindow = $this->groupRecordsByTimeWindow($recordsWithExpiry, $timeWindowStrategy);

        // 合并每个时间窗口的记录
        return $this->mergeGroupedRecords($groupedByWindow, $account);
    }

    /**
     * 查找无过期时间的记录.
     *
     * @return array<Transaction>
     */
    private function findNoExpiryRecords(Account $account, float $minAmount): array
    {
        /** @var array<Transaction> $result */
        $result = $this->transactionRepository
            ->createQueryBuilder('t')
            ->where('t.account = :account')
            ->andWhere('t.balance > 0 AND t.balance <= :minAmount')
            ->andWhere('t.balance = t.amount') // 只合并未被部分消费的记录
            ->andWhere('t.expireTime IS NULL')
            ->setParameter('account', $account)
            ->setParameter('minAmount', $minAmount)
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 查找有过期时间的记录.
     *
     * @return array<Transaction>
     */
    private function findRecordsWithExpiry(Account $account, float $minAmount): array
    {
        /** @var array<Transaction> $recordsWithExpiry */
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
            ->getResult()
        ;

        $this->logger->debug('找到有过期时间的小额积分', [
            'account_id' => $account->getId(),
            'count' => count($recordsWithExpiry),
        ]);

        return $recordsWithExpiry;
    }

    /**
     * 合并分组记录.
     *
     * @param array<string, array<string, mixed>> $groupedByWindow
     */
    private function mergeGroupedRecords(array $groupedByWindow, Account $account): int
    {
        $totalMergeCount = 0;

        foreach ($groupedByWindow as $window) {
            /** @var array<Transaction> $records */
            $records = $window['records'];
            /** @var string $windowKey */
            $windowKey = $window['window'];
            /** @var \DateTimeInterface|null $expireTime */
            $expireTime = $window['earliestExpiry'];

            if (count($records) > 1) {
                $windowMergeCount = $this->mergeRecordGroup(
                    $records,
                    $account,
                    $windowKey,
                    $expireTime
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
     * 按时间窗口分组记录.
     */
    /**
     * @param array<Transaction> $records
     *
     * @return array<string, array<string, mixed>>
     */
    private function groupRecordsByTimeWindow(array $records, TimeWindowStrategy $timeWindowStrategy): array
    {
        $groupedByWindow = [];

        foreach ($records as $record) {
            $expireTime = $record->getExpireTime();
            if (null === $expireTime) {
                continue;
            }

            $windowKey = $this->timeWindowService->getTimeWindowKey($expireTime, $timeWindowStrategy);
            if (!isset($groupedByWindow[$windowKey])) {
                $groupedByWindow[$windowKey] = [
                    'window' => $windowKey,
                    'records' => [],
                    'earliestExpiry' => $expireTime,
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
     * 合并一组记录.
     */
    /**
     * @param array<Transaction> $records
     */
    private function mergeRecordGroup(
        array $records,
        Account $account,
        string $windowKey,
        ?\DateTimeInterface $expireTime = null,
    ): int {
        if (count($records) <= 1) {
            return 0;
        }

        // 创建一条新的合并记录
        $balanceData = $this->calculateTotalBalance($records);
        $mergedRecord = $this->createMergedRecord($account, $windowKey, $balanceData['balance'], $expireTime, $balanceData['recordIds']);

        // 将原小额记录标记为已消费并记录合并日志
        $this->consumeOriginalRecords($records, $mergedRecord);

        // 提交更改
        $this->entityManager->flush();

        return count($records);
    }

    /**
     * 计算总余额并收集记录ID.
     *
     * 注意：此方法不考虑并发，在设计上假设小额流水合并操作的并发冲突概率极低，
     * 且业务上可以容忍少量的计算差异。如需要严格的并发控制，可以考虑：
     * 1. 使用数据库行级锁
     * 2. 使用 Redis 分布式锁
     * 3. 实现乐观锁机制
     */
    /**
     * @param array<Transaction> $records
     *
     * @return array{balance: float, recordIds: array<string>}
     */
    private function calculateTotalBalance(array $records): array
    {
        $totalBalance = 0;
        $recordIds = [];
        foreach ($records as $record) {
            $totalBalance += floatval($record->getBalance());
            $recordId = $record->getId();
            if (null !== $recordId) {
                $recordIds[] = $recordId;
            }
        }

        return ['balance' => $totalBalance, 'recordIds' => $recordIds];
    }

    /**
     * 创建合并记录.
     */
    /**
     * @param array<string> $recordIds
     */
    private function createMergedRecord(
        Account $account,
        string $windowKey,
        float $totalBalance,
        ?\DateTimeInterface $expireTime,
        array $recordIds,
    ): Transaction {
        $mergedRecord = new Transaction();
        $mergedRecord->setAccount($account);
        $mergedRecord->setCurrency($account->getCurrency());
        $mergedRecord->setEventNo('MERGE_'.$account->getId().'_'.$windowKey.'_'.time().'_'.rand(1000, 9999));
        $mergedRecord->setAmount((string) $totalBalance);
        $mergedRecord->setBalance((string) $totalBalance);
        $mergedRecord->setExpireTime($expireTime);
        $mergedRecord->setRemark('合并'.count($recordIds).'条小额积分 ('.$windowKey.')');
        $mergedRecord->setContext([
            'merged_records' => $recordIds,
            'merge_strategy' => $windowKey,
            'merge_time' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $this->entityManager->persist($mergedRecord);

        return $mergedRecord;
    }

    /**
     * 消费原始记录.
     *
     * 注意：此方法不考虑并发，在设计上假设小额流水合并操作的并发冲突概率极低，
     * 且业务上可以容忍少量的记录差异。如需要严格的并发控制，可以考虑：
     * 1. 使用数据库事务锁定
     * 2. 使用乐观锁版本控制
     * 3. 实现分布式锁机制
     */
    /**
     * @param array<Transaction> $records
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
     * 创建消费日志.
     *
     * 注意：此方法不考虑并发，在设计上假设小额流水合并操作的并发冲突概率极低，
     * 且业务上可以容忍少量的日志记录差异。如需要严格的并发控制，可以考虑：
     * 1. 使用数据库事务保证日志一致性
     * 2. 实现异步日志记录
     * 3. 使用队列处理日志写入
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
