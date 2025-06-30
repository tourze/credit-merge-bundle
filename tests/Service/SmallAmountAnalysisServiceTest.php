<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\Transaction;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Service\SmallAmountAnalysisService;
use CreditMergeBundle\Service\TimeWindowService;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SmallAmountAnalysisServiceTest extends TestCase
{
    private SmallAmountAnalysisService $service;
    private TransactionRepository&MockObject $transactionRepository;
    private TimeWindowService&MockObject $timeWindowService;

    protected function setUp(): void
    {
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->timeWindowService = $this->createMock(TimeWindowService::class);

        $this->service = new SmallAmountAnalysisService(
            $this->transactionRepository,
            $this->timeWindowService
        );
    }

    public function testFetchSmallAmountBasicStats(): void
    {
        $account = $this->createMock(Account::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getSingleResult')->willReturn(['count' => 10, 'total' => 50.0]);

        $stats = $this->service->fetchSmallAmountBasicStats($account, 10.0);

        $this->assertEquals(10, $stats['count']);
        $this->assertEquals(50.0, $stats['total']);
    }

    public function testFetchNoExpiryStats(): void
    {
        $account = $this->createMock(Account::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getSingleResult')->willReturn(['count' => 5, 'total' => 25.0]);

        $stats = $this->service->fetchNoExpiryStats($account, 10.0);

        $this->assertEquals(5, $stats['count']);
        $this->assertEquals(25.0, $stats['total']);
    }

    public function testAddNoExpiryStatsToResult(): void
    {
        $account = $this->createMock(Account::class);
        $stats = $this->createMock(SmallAmountStats::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getSingleResult')->willReturn(['count' => 5, 'total' => 25.0]);

        $stats->expects($this->once())
            ->method('addGroupStats')
            ->with('no_expiry', 5, 25.0);

        $this->service->addNoExpiryStatsToResult($account, 10.0, $stats);
    }

    public function testAddNoExpiryStatsToResultWithZeroCount(): void
    {
        $account = $this->createMock(Account::class);
        $stats = $this->createMock(SmallAmountStats::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->transactionRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getSingleResult')->willReturn(['count' => 0, 'total' => 0]);

        $stats->expects($this->never())->method('addGroupStats');

        $this->service->addNoExpiryStatsToResult($account, 10.0, $stats);
    }

    public function testGroupRecordsByTimeWindowForStats(): void
    {
        $expireTime1 = new \DateTimeImmutable('2024-01-15');
        $expireTime2 = new \DateTimeImmutable('2024-01-20');
        $expireTime3 = new \DateTimeImmutable('2024-02-10');

        $record1 = $this->createMock(Transaction::class);
        $record1->method('getExpireTime')->willReturn($expireTime1);
        $record1->method('getBalance')->willReturn('5.0');

        $record2 = $this->createMock(Transaction::class);
        $record2->method('getExpireTime')->willReturn($expireTime2);
        $record2->method('getBalance')->willReturn('3.0');

        $record3 = $this->createMock(Transaction::class);
        $record3->method('getExpireTime')->willReturn($expireTime3);
        $record3->method('getBalance')->willReturn('7.0');

        $this->timeWindowService->method('getTimeWindowKey')
            ->willReturnCallback(function ($expireTime) {
                return $expireTime->format('Y-m');
            });

        $grouped = $this->service->groupRecordsByTimeWindowForStats(
            [$record1, $record2, $record3],
            TimeWindowStrategy::MONTH
        );

        $this->assertCount(2, $grouped);
        $this->assertEquals('2024-01', $grouped['2024-01']['window']);
        $this->assertEquals(2, $grouped['2024-01']['count']);
        $this->assertEquals(8.0, $grouped['2024-01']['total']);
        $this->assertEquals($expireTime1, $grouped['2024-01']['earliestExpiry']);

        $this->assertEquals('2024-02', $grouped['2024-02']['window']);
        $this->assertEquals(1, $grouped['2024-02']['count']);
        $this->assertEquals(7.0, $grouped['2024-02']['total']);
    }

    public function testCalculateTotalAmount(): void
    {
        $record1 = $this->createMock(Transaction::class);
        $record1->method('getBalance')->willReturn('5.5');

        $record2 = $this->createMock(Transaction::class);
        $record2->method('getBalance')->willReturn('3.3');

        $record3 = $this->createMock(Transaction::class);
        $record3->method('getBalance')->willReturn('1.2');

        $total = $this->service->calculateTotalAmount([$record1, $record2, $record3]);

        $this->assertEquals(10.0, $total);
    }

    public function testGroupRecordsByTimeWindows(): void
    {
        $expireTime = new \DateTimeImmutable('2024-01-15');
        
        $record = $this->createMock(Transaction::class);
        $record->method('getExpireTime')->willReturn($expireTime);
        $record->method('getBalance')->willReturn('5.0');

        $this->timeWindowService->method('getTimeWindowKey')
            ->willReturnCallback(function ($expireTime, $strategy) {
                switch ($strategy) {
                    case TimeWindowStrategy::DAY:
                        return $expireTime->format('Y-m-d');
                    case TimeWindowStrategy::WEEK:
                        return $expireTime->format('Y-W');
                    case TimeWindowStrategy::MONTH:
                        return $expireTime->format('Y-m');
                }
            });

        $result = $this->service->groupRecordsByTimeWindows([$record]);

        $this->assertArrayHasKey('day', $result);
        $this->assertArrayHasKey('week', $result);
        $this->assertArrayHasKey('month', $result);

        $this->assertCount(1, $result['day']);
        $this->assertEquals('2024-01-15', $result['day'][0]['window_key']);
        $this->assertEquals(1, $result['day'][0]['count']);
        $this->assertEquals(5.0, $result['day'][0]['total_amount']);

        $this->assertCount(1, $result['week']);
        $this->assertEquals('2024-03', $result['week'][0]['window_key']); // 第3周

        $this->assertCount(1, $result['month']);
        $this->assertEquals('2024-01', $result['month'][0]['window_key']);
    }
}