<?php

namespace CreditMergeBundle\Tests\Model;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use PHPUnit\Framework\TestCase;

class SmallAmountStatsTest extends TestCase
{
    private Account $account;
    
    protected function setUp(): void
    {
        $this->account = $this->createMock(Account::class);
        $this->account->method('getId')->willReturn(123);
    }
    
    /**
     * 测试基本构造函数和获取方法
     */
    public function testConstructorAndGetters(): void
    {
        $stats = new SmallAmountStats(
            $this->account,
            10,
            50.0,
            5.0,
            TimeWindowStrategy::MONTH
        );
        
        $this->assertSame($this->account, $stats->getAccount());
        $this->assertSame(10, $stats->getCount());
        $this->assertSame(50.0, $stats->getTotal());
        $this->assertSame(5.0, $stats->getThreshold());
        $this->assertSame(TimeWindowStrategy::MONTH, $stats->getStrategy());
    }
    
    /**
     * 测试策略设置方法
     */
    public function testSetStrategy(): void
    {
        $stats = new SmallAmountStats(
            $this->account,
            10,
            50.0,
            5.0,
            TimeWindowStrategy::DAY
        );
        
        $this->assertSame(TimeWindowStrategy::DAY, $stats->getStrategy());
        
        $result = $stats->setStrategy(TimeWindowStrategy::WEEK);
        
        $this->assertSame($stats, $result, '方法应返回自身实例以支持链式调用');
        $this->assertSame(TimeWindowStrategy::WEEK, $stats->getStrategy());
    }
    
    /**
     * 测试添加和获取分组统计
     */
    public function testAddAndGetGroupStats(): void
    {
        $stats = new SmallAmountStats(
            $this->account,
            10,
            50.0,
            5.0
        );
        
        $expiry = new \DateTime('2023-12-31');
        
        $result = $stats->addGroupStats('group1', 5, 25.0, $expiry);
        
        $this->assertSame($stats, $result, '方法应返回自身实例以支持链式调用');
        
        $groupStats = $stats->getGroupStats();
        $this->assertArrayHasKey('group1', $groupStats);
        $this->assertSame(5, $groupStats['group1']['count']);
        $this->assertSame(25.0, $groupStats['group1']['total']);
        $this->assertSame($expiry->format('Y-m-d H:i:s'), $groupStats['group1']['earliest_expiry']);
        
        // 测试无过期时间的情况
        $stats->addGroupStats('group2', 5, 25.0);
        $groupStats = $stats->getGroupStats();
        $this->assertArrayHasKey('group2', $groupStats);
        $this->assertNull($groupStats['group2']['earliest_expiry']);
    }
    
    /**
     * 测试hasMergeableRecords方法
     */
    public function testHasMergeableRecords(): void
    {
        // 可合并的情况（记录数 > 1）
        $statsWithMergeable = new SmallAmountStats(
            $this->account,
            10,
            50.0,
            5.0
        );
        $this->assertTrue($statsWithMergeable->hasMergeableRecords());
        
        // 不可合并的情况（记录数 <= 1）
        $statsWithoutMergeable = new SmallAmountStats(
            $this->account,
            1,
            5.0,
            5.0
        );
        $this->assertFalse($statsWithoutMergeable->hasMergeableRecords());
        
        $statsWithZeroRecords = new SmallAmountStats(
            $this->account,
            0,
            0.0,
            5.0
        );
        $this->assertFalse($statsWithZeroRecords->hasMergeableRecords());
    }
    
    /**
     * 测试getPotentialRecordReduction方法 - 无分组统计的情况
     */
    public function testGetPotentialRecordReduction_withoutGroupStats(): void
    {
        // 没有可合并记录的情况
        $statsWithoutMergeable = new SmallAmountStats(
            $this->account,
            1,
            5.0,
            5.0
        );
        $this->assertSame(0, $statsWithoutMergeable->getPotentialRecordReduction());
        
        // 有可合并记录但没有分组的情况
        $statsWithMergeable = new SmallAmountStats(
            $this->account,
            10,
            50.0,
            5.0
        );
        $this->assertSame(9, $statsWithMergeable->getPotentialRecordReduction());
    }
    
    /**
     * 测试getPotentialRecordReduction方法 - 有分组统计的情况
     */
    public function testGetPotentialRecordReduction_withGroupStats(): void
    {
        $stats = new SmallAmountStats(
            $this->account,
            20,
            100.0,
            5.0
        );
        
        // 添加分组：第一组有5条记录，第二组有10条记录，第三组只有1条记录
        $stats->addGroupStats('group1', 5, 25.0);
        $stats->addGroupStats('group2', 10, 50.0);
        $stats->addGroupStats('group3', 1, 5.0);
        
        // 预期减少的记录数 = (5-1) + (10-1) + (1-1)（每组减少的记录数之和）= 4 + 9 + 0 = 13
        $this->assertSame(13, $stats->getPotentialRecordReduction());
    }
    
    /**
     * 测试getMergeEfficiency方法
     */
    public function testGetMergeEfficiency(): void
    {
        // 没有记录或只有一条记录时，效率为0
        $statsWithZeroRecords = new SmallAmountStats(
            $this->account,
            0,
            0.0,
            5.0
        );
        $this->assertSame(0.0, $statsWithZeroRecords->getMergeEfficiency());
        
        $statsWithOneRecord = new SmallAmountStats(
            $this->account,
            1,
            5.0,
            5.0
        );
        $this->assertSame(0.0, $statsWithOneRecord->getMergeEfficiency());
        
        // 有记录且可合并时
        $stats = new SmallAmountStats(
            $this->account,
            20,
            100.0,
            5.0
        );
        
        // 添加分组使得可减少13条记录
        $stats->addGroupStats('group1', 5, 25.0);
        $stats->addGroupStats('group2', 10, 50.0);
        $stats->addGroupStats('group3', 1, 5.0);
        
        // 效率 = 13/20 * 100 = 65.0
        $this->assertSame(65.0, $stats->getMergeEfficiency());
    }
    
    /**
     * 测试getAverageAmount方法
     */
    public function testGetAverageAmount(): void
    {
        // 没有记录时，平均金额为0
        $statsWithZeroRecords = new SmallAmountStats(
            $this->account,
            0,
            0.0,
            5.0
        );
        $this->assertSame(0.0, $statsWithZeroRecords->getAverageAmount());
        
        // 有记录时，平均金额 = 总金额/记录数
        $stats = new SmallAmountStats(
            $this->account,
            10,
            50.0,
            5.0
        );
        $this->assertSame(5.0, $stats->getAverageAmount());
    }
    
    /**
     * 测试jsonSerialize方法
     */
    public function testJsonSerialize(): void
    {
        $stats = new SmallAmountStats(
            $this->account,
            10,
            50.0,
            5.0,
            TimeWindowStrategy::MONTH
        );
        
        $stats->addGroupStats('group1', 5, 25.0);
        
        $expected = [
            'account_id' => 123,
            'count' => 10,
            'total' => 50.0,
            'threshold' => 5.0,
            'strategy' => 'month',
            'average_amount' => 5.0,
            'has_mergeable_records' => true,
            'potential_reduction' => 4, // 基于添加的分组统计
            'merge_efficiency' => 40.0, // 4/10 * 100 = 40.0
            'group_stats' => [
                'group1' => [
                    'count' => 5,
                    'total' => 25.0,
                    'earliest_expiry' => null
                ]
            ],
        ];
        
        $this->assertEquals($expected, $stats->jsonSerialize());
    }
    
    /**
     * 测试静态工厂方法createEmpty
     */
    public function testCreateEmpty(): void
    {
        $emptyStats = SmallAmountStats::createEmpty($this->account, 10.0);
        
        $this->assertSame($this->account, $emptyStats->getAccount());
        $this->assertSame(0, $emptyStats->getCount());
        $this->assertSame(0.0, $emptyStats->getTotal());
        $this->assertSame(10.0, $emptyStats->getThreshold());
        $this->assertNull($emptyStats->getStrategy());
        $this->assertEmpty($emptyStats->getGroupStats());
    }
} 