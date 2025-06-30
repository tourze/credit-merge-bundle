<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\ConsumeLog;
use CreditBundle\Entity\Transaction;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Service\CreditMergeOperationService;
use CreditMergeBundle\Service\TimeWindowService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreditMergeOperationServiceTest extends TestCase
{
    private CreditMergeOperationService $service;
    private EntityManagerInterface&MockObject $entityManager;
    private TransactionRepository&MockObject $transactionRepository;
    private LoggerInterface&MockObject $logger;
    private TimeWindowService&MockObject $timeWindowService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->timeWindowService = $this->createMock(TimeWindowService::class);

        $this->service = new CreditMergeOperationService(
            $this->entityManager,
            $this->transactionRepository,
            $this->logger,
            $this->timeWindowService
        );
    }

    public function testMergeNoExpiryRecordsWithNoRecords(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(1);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        $result = $this->service->mergeNoExpiryRecords($account, 10.0);

        $this->assertEquals(0, $result);
    }

    public function testMergeNoExpiryRecordsWithMultipleRecords(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(1);
        
        $currency = $this->createMock(\CreditBundle\Entity\Currency::class);
        $account->method('getCurrency')->willReturn($currency);

        $transaction1 = $this->createMock(Transaction::class);
        $transaction1->method('getId')->willReturn('1');
        $transaction1->method('getBalance')->willReturn('5.00');
        $transaction1->method('getAmount')->willReturn('5.00');

        $transaction2 = $this->createMock(Transaction::class);
        $transaction2->method('getId')->willReturn('2');
        $transaction2->method('getBalance')->willReturn('3.00');
        $transaction2->method('getAmount')->willReturn('3.00');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([$transaction1, $transaction2]);

        $this->entityManager->expects($this->exactly(5))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->mergeNoExpiryRecords($account, 10.0);

        $this->assertEquals(2, $result);
    }

    public function testMergeExpiryRecordsWithNoRecords(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(1);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        $result = $this->service->mergeExpiryRecords($account, 10.0, TimeWindowStrategy::MONTH);

        $this->assertEquals(0, $result);
    }

    public function testMergeExpiryRecordsWithMultipleRecordsInSameWindow(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(1);
        
        $currency = $this->createMock(\CreditBundle\Entity\Currency::class);
        $account->method('getCurrency')->willReturn($currency);

        $expireTime = new \DateTimeImmutable('2024-12-31');

        $transaction1 = $this->createMock(Transaction::class);
        $transaction1->method('getId')->willReturn('1');
        $transaction1->method('getBalance')->willReturn('5.00');
        $transaction1->method('getAmount')->willReturn('5.00');
        $transaction1->method('getExpireTime')->willReturn($expireTime);

        $transaction2 = $this->createMock(Transaction::class);
        $transaction2->method('getId')->willReturn('2');
        $transaction2->method('getBalance')->willReturn('3.00');
        $transaction2->method('getAmount')->willReturn('3.00');
        $transaction2->method('getExpireTime')->willReturn($expireTime);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([$transaction1, $transaction2]);

        $this->timeWindowService->method('getTimeWindowKey')
            ->willReturn('2024-12');

        $this->entityManager->expects($this->exactly(5))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->mergeExpiryRecords($account, 10.0, TimeWindowStrategy::MONTH);

        $this->assertEquals(2, $result);
    }

    public function testMergeExpiryRecordsWithRecordsInDifferentWindows(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(1);
        
        $currency = $this->createMock(\CreditBundle\Entity\Currency::class);
        $account->method('getCurrency')->willReturn($currency);

        $expireTime1 = new \DateTimeImmutable('2024-11-30');
        $expireTime2 = new \DateTimeImmutable('2024-12-31');

        $transaction1 = $this->createMock(Transaction::class);
        $transaction1->method('getId')->willReturn('1');
        $transaction1->method('getBalance')->willReturn('5.00');
        $transaction1->method('getAmount')->willReturn('5.00');
        $transaction1->method('getExpireTime')->willReturn($expireTime1);

        $transaction2 = $this->createMock(Transaction::class);
        $transaction2->method('getId')->willReturn('2');
        $transaction2->method('getBalance')->willReturn('3.00');
        $transaction2->method('getAmount')->willReturn('3.00');
        $transaction2->method('getExpireTime')->willReturn($expireTime2);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([$transaction1, $transaction2]);

        $this->timeWindowService->method('getTimeWindowKey')
            ->willReturnCallback(function ($expireTime) {
                return $expireTime->format('Y-m');
            });

        // 每个窗口只有一条记录，不会触发合并
        $result = $this->service->mergeExpiryRecords($account, 10.0, TimeWindowStrategy::MONTH);

        $this->assertEquals(0, $result);
    }
}