<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Service\CreditMergeOperationService;
use CreditMergeBundle\Service\CreditMergeService;
use CreditMergeBundle\Service\CreditMergeStatsService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreditMergeServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private LoggerInterface $logger;
    private CreditMergeOperationService $operationService;
    private CreditMergeStatsService $statsService;
    private CreditMergeService $service;
    private Account $account;
    
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->operationService = $this->createMock(CreditMergeOperationService::class);
        $this->statsService = $this->createMock(CreditMergeStatsService::class);
        
        $this->service = new CreditMergeService(
            $this->entityManager,
            $this->logger,
            $this->operationService,
            $this->statsService
        );
        
        $this->account = $this->createMock(Account::class);
        $this->account->method('getId')->willReturn(123);
    }
    
    /**
     * 测试合并小额积分方法-正常流程
     */
    public function testMergeSmallAmounts_successfulMerge(): void
    {
        // 设置预期行为
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');
        
        $this->operationService->expects($this->once())
            ->method('mergeNoExpiryRecords')
            ->with($this->account, 5.0)
            ->willReturn(3);
            
        $this->operationService->expects($this->once())
            ->method('mergeExpiryRecords')
            ->with($this->account, 5.0, TimeWindowStrategy::MONTH)
            ->willReturn(7);
        
        // 设置日志记录预期
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function($message, $context) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 1) {
                    $this->assertStringContainsString('开始合并小额积分', $message);
                    $this->assertEquals(123, $context['account_id']);
                    $this->assertEquals(5.0, $context['min_amount']);
                    $this->assertEquals(100, $context['batch_size']);
                    $this->assertEquals('month', $context['strategy']);
                } else {
                    $this->assertStringContainsString('积分合并完成', $message);
                    $this->assertEquals(123, $context['account_id']);
                    $this->assertEquals(10, $context['merge_count']);
                    $this->assertEquals('month', $context['strategy']);
                }
                
                return true;
            });
        
        // 执行方法
        $result = $this->service->mergeSmallAmounts($this->account, 5.0, 100, TimeWindowStrategy::MONTH);
        
        // 验证结果
        $this->assertEquals(10, $result, '合并结果应返回被合并的总记录数'); // 3 + 7 = 10
    }
    
    /**
     * 测试合并小额积分方法-合并过程异常
     */
    public function testMergeSmallAmounts_exceptionHandling(): void
    {
        $exception = new \Exception('测试异常');
        
        // 设置预期行为
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->never())->method('commit');
        $this->connection->expects($this->once())->method('rollBack');
        
        $this->operationService->expects($this->once())
            ->method('mergeNoExpiryRecords')
            ->willThrowException($exception);
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('积分合并失败'),
                $this->callback(function ($context) {
                    return $context['account_id'] === 123 
                        && $context['exception'] === '测试异常'
                        && is_string($context['trace']);
                })
            );
        
        // 期望抛出异常
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('测试异常');
        
        // 执行方法
        $this->service->mergeSmallAmounts($this->account);
    }
    
    /**
     * 测试获取小额积分统计信息方法
     */
    public function testGetSmallAmountStats(): void
    {
        // 创建模拟统计对象
        $stats = new SmallAmountStats($this->account, 10, 50.0, 5.0);
        
        // 设置预期行为
        $this->statsService->expects($this->once())
            ->method('getSmallAmountStats')
            ->with($this->account, 5.0)
            ->willReturn($stats);
        
        // 执行方法
        $result = $this->service->getSmallAmountStats($this->account, 5.0);
        
        // 验证结果
        $this->assertSame($stats, $result);
        $this->assertEquals(10, $result->getCount());
        $this->assertEquals(50.0, $result->getTotal());
        $this->assertEquals(5.0, $result->getThreshold());
    }
    
    /**
     * 测试使用不同阈值获取小额积分统计信息
     */
    public function testGetSmallAmountStats_withDifferentThreshold(): void
    {
        // 创建模拟统计对象
        $stats = new SmallAmountStats($this->account, 5, 30.0, 10.0);
        
        // 设置预期行为
        $this->statsService->expects($this->once())
            ->method('getSmallAmountStats')
            ->with($this->account, 10.0)
            ->willReturn($stats);
        
        // 执行方法
        $result = $this->service->getSmallAmountStats($this->account, 10.0);
        
        // 验证结果
        $this->assertSame($stats, $result);
        $this->assertEquals(5, $result->getCount());
        $this->assertEquals(30.0, $result->getTotal());
        $this->assertEquals(10.0, $result->getThreshold());
    }
    
    /**
     * 测试获取详细的小额积分统计信息方法
     */
    public function testGetDetailedSmallAmountStats(): void
    {
        // 创建模拟详细统计对象
        $stats = new SmallAmountStats($this->account, 10, 50.0, 5.0, TimeWindowStrategy::MONTH);
        $stats->addGroupStats('2023-10', 5, 25.0);
        $stats->addGroupStats('2023-11', 5, 25.0);
        
        // 设置预期行为
        $this->statsService->expects($this->once())
            ->method('getDetailedSmallAmountStats')
            ->with($this->account, 5.0, TimeWindowStrategy::MONTH)
            ->willReturn($stats);
        
        // 执行方法
        $result = $this->service->getDetailedSmallAmountStats($this->account, 5.0, TimeWindowStrategy::MONTH);
        
        // 验证结果
        $this->assertSame($stats, $result);
        $this->assertEquals(10, $result->getCount());
        $this->assertEquals(50.0, $result->getTotal());
        $this->assertEquals(5.0, $result->getThreshold());
        $this->assertSame(TimeWindowStrategy::MONTH, $result->getStrategy());
        
        $groupStats = $result->getGroupStats();
        $this->assertCount(2, $groupStats);
        $this->assertArrayHasKey('2023-10', $groupStats);
        $this->assertArrayHasKey('2023-11', $groupStats);
    }
    
    /**
     * 测试使用不同策略获取详细的小额积分统计信息
     */
    public function testGetDetailedSmallAmountStats_withDifferentStrategy(): void
    {
        // 创建模拟详细统计对象
        $stats = new SmallAmountStats($this->account, 10, 50.0, 5.0, TimeWindowStrategy::DAY);
        $stats->addGroupStats('2023-10-15', 3, 15.0);
        $stats->addGroupStats('2023-10-16', 7, 35.0);
        
        // 设置预期行为
        $this->statsService->expects($this->once())
            ->method('getDetailedSmallAmountStats')
            ->with($this->account, 5.0, TimeWindowStrategy::DAY)
            ->willReturn($stats);
        
        // 执行方法
        $result = $this->service->getDetailedSmallAmountStats($this->account, 5.0, TimeWindowStrategy::DAY);
        
        // 验证结果
        $this->assertSame($stats, $result);
        $this->assertEquals(10, $result->getCount());
        $this->assertEquals(50.0, $result->getTotal());
        $this->assertEquals(5.0, $result->getThreshold());
        $this->assertSame(TimeWindowStrategy::DAY, $result->getStrategy());
        
        $groupStats = $result->getGroupStats();
        $this->assertCount(2, $groupStats);
        $this->assertArrayHasKey('2023-10-15', $groupStats);
        $this->assertArrayHasKey('2023-10-16', $groupStats);
    }
} 