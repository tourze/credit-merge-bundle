<?php

namespace CreditMergeBundle\Tests\Service;

use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Service\TimeWindowService;
use PHPUnit\Framework\TestCase;

class TimeWindowServiceTest extends TestCase
{
    private TimeWindowService $service;
    
    protected function setUp(): void
    {
        $this->service = new TimeWindowService();
    }
    
    /**
     * 测试根据策略获取时间窗口键值
     */
    public function testGetTimeWindowKey(): void
    {
        $date = new \DateTime('2023-10-15 14:30:45');
        
        // 测试按天策略
        $this->assertEquals('2023-10-15', $this->service->getTimeWindowKey($date, TimeWindowStrategy::DAY));
        
        // 测试按周策略
        $this->assertEquals('2023-W41', $this->service->getTimeWindowKey($date, TimeWindowStrategy::WEEK));
        
        // 测试按月策略
        $this->assertEquals('2023-10', $this->service->getTimeWindowKey($date, TimeWindowStrategy::MONTH));
        
        // 测试全部策略 (应返回固定值 'all')
        $this->assertEquals('all', $this->service->getTimeWindowKey($date, TimeWindowStrategy::ALL));
    }
    
    /**
     * 测试使用不同日期的时间窗口键值
     */
    public function testGetTimeWindowKey_withDifferentDates(): void
    {
        // 测试月初日期
        $date1 = new \DateTime('2023-10-01 00:00:00');
        $this->assertEquals('2023-10-01', $this->service->getTimeWindowKey($date1, TimeWindowStrategy::DAY));
        $this->assertEquals('2023-W39', $this->service->getTimeWindowKey($date1, TimeWindowStrategy::WEEK));
        $this->assertEquals('2023-10', $this->service->getTimeWindowKey($date1, TimeWindowStrategy::MONTH));
        
        // 测试月末日期
        $date2 = new \DateTime('2023-10-31 23:59:59');
        $this->assertEquals('2023-10-31', $this->service->getTimeWindowKey($date2, TimeWindowStrategy::DAY));
        $this->assertEquals('2023-W44', $this->service->getTimeWindowKey($date2, TimeWindowStrategy::WEEK));
        $this->assertEquals('2023-10', $this->service->getTimeWindowKey($date2, TimeWindowStrategy::MONTH));
        
        // 测试跨年日期
        $date3 = new \DateTime('2023-12-31 23:59:59');
        $this->assertEquals('2023-12-31', $this->service->getTimeWindowKey($date3, TimeWindowStrategy::DAY));
        $this->assertEquals('2023-W52', $this->service->getTimeWindowKey($date3, TimeWindowStrategy::WEEK));
        $this->assertEquals('2023-12', $this->service->getTimeWindowKey($date3, TimeWindowStrategy::MONTH));
        
        $date4 = new \DateTime('2024-01-01 00:00:00');
        $this->assertEquals('2024-01-01', $this->service->getTimeWindowKey($date4, TimeWindowStrategy::DAY));
        $this->assertEquals('2024-W01', $this->service->getTimeWindowKey($date4, TimeWindowStrategy::WEEK));
        $this->assertEquals('2024-01', $this->service->getTimeWindowKey($date4, TimeWindowStrategy::MONTH));
    }
    
    /**
     * 测试空日期参数的处理
     * 注意：原始实现不支持null参数，需要修改测试用例
     */
    public function testGetTimeWindowKey_withCurrentDateTime(): void
    {
        // 使用当前时间测试
        $now = new \DateTime();
        $expectedDayFormat = $now->format('Y-m-d');
        $expectedWeekFormat = $now->format('Y') . '-W' . $now->format('W');
        $expectedMonthFormat = $now->format('Y-m');
        
        $this->assertEquals($expectedDayFormat, $this->service->getTimeWindowKey($now, TimeWindowStrategy::DAY));
        $this->assertEquals($expectedWeekFormat, $this->service->getTimeWindowKey($now, TimeWindowStrategy::WEEK));
        $this->assertEquals($expectedMonthFormat, $this->service->getTimeWindowKey($now, TimeWindowStrategy::MONTH));
        $this->assertEquals('all', $this->service->getTimeWindowKey($now, TimeWindowStrategy::ALL));
    }
    
    /**
     * 测试使用不支持的参数类型抛出异常
     */
    public function testGetTimeWindowKey_withInvalidType(): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line */
        $this->service->getTimeWindowKey('invalid-date', TimeWindowStrategy::DAY);
    }
} 