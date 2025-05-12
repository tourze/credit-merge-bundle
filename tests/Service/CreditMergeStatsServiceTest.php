<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Service\CreditMergeStatsService;
use CreditMergeBundle\Service\MergePotentialAnalysisService;
use CreditMergeBundle\Service\SmallAmountAnalysisService;
use PHPUnit\Framework\TestCase;

class CreditMergeStatsServiceTest extends TestCase
{
    private SmallAmountAnalysisService $smallAmountAnalysisService;
    private MergePotentialAnalysisService $mergePotentialAnalysisService;
    private CreditMergeStatsService $service;
    private Account $account;
    
    protected function setUp(): void
    {
        $this->smallAmountAnalysisService = $this->createMock(SmallAmountAnalysisService::class);
        $this->mergePotentialAnalysisService = $this->createMock(MergePotentialAnalysisService::class);
        
        $this->service = new CreditMergeStatsService(
            $this->smallAmountAnalysisService,
            $this->mergePotentialAnalysisService
        );
        
        $this->account = $this->createMock(Account::class);
        $this->account->method('getId')->willReturn(123);
    }
    
    /**
     * 测试获取小额积分统计 - 无小额积分的情况
     */
    public function testGetSmallAmountStats_withNoSmallAmounts(): void
    {
        // 设置预期行为：没有小额积分记录
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('fetchSmallAmountBasicStats')
            ->with($this->account, 5.0)
            ->willReturn(['count' => 0, 'total' => 0]);
        
        // 执行方法
        $result = $this->service->getSmallAmountStats($this->account, 5.0);
        
        // 验证结果
        $this->assertInstanceOf(SmallAmountStats::class, $result);
        $this->assertSame($this->account, $result->getAccount());
        $this->assertEquals(0, $result->getCount());
        $this->assertEquals(0.0, $result->getTotal());
        $this->assertEquals(5.0, $result->getThreshold());
        $this->assertFalse($result->hasMergeableRecords());
    }
    
    /**
     * 测试获取小额积分统计 - 有小额积分的情况
     */
    public function testGetSmallAmountStats_withSmallAmounts(): void
    {
        // 设置预期行为：返回小额积分记录统计
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('fetchSmallAmountBasicStats')
            ->with($this->account, 5.0)
            ->willReturn(['count' => 3, 'total' => 6.5]);
        
        // 执行方法
        $result = $this->service->getSmallAmountStats($this->account, 5.0);
        
        // 验证结果
        $this->assertInstanceOf(SmallAmountStats::class, $result);
        $this->assertSame($this->account, $result->getAccount());
        $this->assertEquals(3, $result->getCount());
        $this->assertEquals(6.5, $result->getTotal());
        $this->assertEquals(5.0, $result->getThreshold());
        $this->assertTrue($result->hasMergeableRecords());
    }
    
    /**
     * 测试获取详细小额积分统计 - 无小额积分的情况
     */
    public function testGetDetailedSmallAmountStats_withNoSmallAmounts(): void
    {
        // 设置预期行为：没有小额积分记录
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('fetchSmallAmountBasicStats')
            ->with($this->account, 5.0)
            ->willReturn(['count' => 0, 'total' => 0]);
        
        // 不应调用其他方法，因为没有记录
        $this->smallAmountAnalysisService->expects($this->never())
            ->method('addNoExpiryStatsToResult');
        $this->smallAmountAnalysisService->expects($this->never())
            ->method('addExpiryStatsToResult');
        
        // 执行方法
        $result = $this->service->getDetailedSmallAmountStats(
            $this->account, 
            5.0, 
            TimeWindowStrategy::MONTH
        );
        
        // 验证结果
        $this->assertInstanceOf(SmallAmountStats::class, $result);
        $this->assertSame($this->account, $result->getAccount());
        $this->assertEquals(0, $result->getCount());
        $this->assertEquals(0.0, $result->getTotal());
        $this->assertEquals(5.0, $result->getThreshold());
        $this->assertSame(TimeWindowStrategy::MONTH, $result->getStrategy());
        $this->assertEmpty($result->getGroupStats());
    }
    
    /**
     * 测试获取详细小额积分统计 - 有小额积分但无过期时间的情况
     */
    public function testGetDetailedSmallAmountStats_withNonExpiryCredits(): void
    {
        // 设置基础统计服务的行为
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('fetchSmallAmountBasicStats')
            ->with($this->account, 5.0)
            ->willReturn(['count' => 3, 'total' => 6.5]);
        
        // 模拟添加无过期时间记录的行为
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('addNoExpiryStatsToResult')
            ->with($this->account, 5.0, $this->isInstanceOf(SmallAmountStats::class))
            ->willReturnCallback(function($account, $threshold, $stats) {
                $stats->addGroupStats('no_expiry', 3, 6.5, null);
            });
        
        // 模拟添加有过期时间记录的行为 - 在这种情况下没有有过期时间的记录
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('addExpiryStatsToResult')
            ->with(
                $this->account, 
                5.0, 
                TimeWindowStrategy::MONTH, 
                $this->isInstanceOf(SmallAmountStats::class)
            );
        
        // 执行方法
        $result = $this->service->getDetailedSmallAmountStats(
            $this->account, 
            5.0, 
            TimeWindowStrategy::MONTH
        );
        
        // 验证结果
        $this->assertInstanceOf(SmallAmountStats::class, $result);
        $this->assertSame($this->account, $result->getAccount());
        $this->assertEquals(3, $result->getCount());
        $this->assertEquals(6.5, $result->getTotal());
        $this->assertEquals(5.0, $result->getThreshold());
        $this->assertSame(TimeWindowStrategy::MONTH, $result->getStrategy());
        
        // 检查分组统计
        $groupStats = $result->getGroupStats();
        $this->assertArrayHasKey('no_expiry', $groupStats);
        $this->assertEquals(3, $groupStats['no_expiry']['count']);
        $this->assertEquals(6.5, $groupStats['no_expiry']['total']);
        $this->assertNull($groupStats['no_expiry']['earliest_expiry']);
    }
    
    /**
     * 测试获取详细小额积分统计 - 有小额积分且有过期时间的情况
     */
    public function testGetDetailedSmallAmountStats_withExpiryCredits(): void
    {
        // 创建过期时间
        $expiry1 = new \DateTime('2023-10-15');
        $expiry2 = new \DateTime('2023-11-20');
        
        // 设置基础统计服务的行为
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('fetchSmallAmountBasicStats')
            ->with($this->account, 5.0)
            ->willReturn(['count' => 3, 'total' => 6.5]);
        
        // 模拟添加无过期时间记录的行为 - 没有无过期时间记录
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('addNoExpiryStatsToResult')
            ->with($this->account, 5.0, $this->isInstanceOf(SmallAmountStats::class));
        
        // 模拟添加有过期时间记录的行为
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('addExpiryStatsToResult')
            ->with(
                $this->account, 
                5.0, 
                TimeWindowStrategy::MONTH, 
                $this->isInstanceOf(SmallAmountStats::class)
            )
            ->willReturnCallback(function($account, $threshold, $strategy, $stats) use ($expiry1, $expiry2) {
                $stats->addGroupStats('2023-10', 2, 3.5, $expiry1);
                $stats->addGroupStats('2023-11', 1, 3.0, $expiry2);
            });
        
        // 执行方法
        $result = $this->service->getDetailedSmallAmountStats(
            $this->account, 
            5.0, 
            TimeWindowStrategy::MONTH
        );
        
        // 验证结果
        $this->assertInstanceOf(SmallAmountStats::class, $result);
        $this->assertSame($this->account, $result->getAccount());
        $this->assertEquals(3, $result->getCount());
        $this->assertEquals(6.5, $result->getTotal());
        $this->assertEquals(5.0, $result->getThreshold());
        $this->assertSame(TimeWindowStrategy::MONTH, $result->getStrategy());
        
        // 检查分组统计
        $groupStats = $result->getGroupStats();
        $this->assertCount(2, $groupStats); // 两个时间窗口
        
        $this->assertArrayHasKey('2023-10', $groupStats);
        $this->assertEquals(2, $groupStats['2023-10']['count']);
        $this->assertEquals(3.5, $groupStats['2023-10']['total']);
        $this->assertEquals($expiry1->format('Y-m-d H:i:s'), $groupStats['2023-10']['earliest_expiry']);
        
        $this->assertArrayHasKey('2023-11', $groupStats);
        $this->assertEquals(1, $groupStats['2023-11']['count']);
        $this->assertEquals(3.0, $groupStats['2023-11']['total']);
        $this->assertEquals($expiry2->format('Y-m-d H:i:s'), $groupStats['2023-11']['earliest_expiry']);
    }
    
    /**
     * 测试获取详细小额积分统计 - 混合有无过期时间的情况
     */
    public function testGetDetailedSmallAmountStats_withMixedCredits(): void
    {
        // 创建过期时间
        $expiry = new \DateTime('2023-10-15');
        
        // 设置基础统计服务的行为
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('fetchSmallAmountBasicStats')
            ->with($this->account, 5.0)
            ->willReturn(['count' => 3, 'total' => 6.5]);
        
        // 模拟添加无过期时间记录的行为
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('addNoExpiryStatsToResult')
            ->with($this->account, 5.0, $this->isInstanceOf(SmallAmountStats::class))
            ->willReturnCallback(function($account, $threshold, $stats) {
                $stats->addGroupStats('no_expiry', 2, 4.5, null);
            });
        
        // 模拟添加有过期时间记录的行为
        $this->smallAmountAnalysisService->expects($this->once())
            ->method('addExpiryStatsToResult')
            ->with(
                $this->account, 
                5.0, 
                TimeWindowStrategy::MONTH, 
                $this->isInstanceOf(SmallAmountStats::class)
            )
            ->willReturnCallback(function($account, $threshold, $strategy, $stats) use ($expiry) {
                $stats->addGroupStats('2023-10', 1, 2.0, $expiry);
            });
        
        // 执行方法
        $result = $this->service->getDetailedSmallAmountStats(
            $this->account, 
            5.0, 
            TimeWindowStrategy::MONTH
        );
        
        // 验证结果
        $this->assertInstanceOf(SmallAmountStats::class, $result);
        $this->assertSame($this->account, $result->getAccount());
        $this->assertEquals(3, $result->getCount());
        $this->assertEquals(6.5, $result->getTotal());
        
        // 检查分组统计
        $groupStats = $result->getGroupStats();
        $this->assertCount(2, $groupStats); // 无过期组 + 一个时间窗口组
        
        $this->assertArrayHasKey('no_expiry', $groupStats);
        $this->assertEquals(2, $groupStats['no_expiry']['count']);
        $this->assertEquals(4.5, $groupStats['no_expiry']['total']);
        
        $this->assertArrayHasKey('2023-10', $groupStats);
        $this->assertEquals(1, $groupStats['2023-10']['count']);
        $this->assertEquals(2.0, $groupStats['2023-10']['total']);
        $this->assertEquals($expiry->format('Y-m-d H:i:s'), $groupStats['2023-10']['earliest_expiry']);
    }
} 