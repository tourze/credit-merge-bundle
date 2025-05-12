<?php

namespace CreditMergeBundle\Tests\Enum;

use CreditMergeBundle\Enum\TimeWindowStrategy;
use PHPUnit\Framework\TestCase;

class TimeWindowStrategyTest extends TestCase
{
    /**
     * 测试枚举值是否正确
     */
    public function testEnumValues(): void
    {
        $this->assertSame('day', TimeWindowStrategy::DAY->value);
        $this->assertSame('week', TimeWindowStrategy::WEEK->value);
        $this->assertSame('month', TimeWindowStrategy::MONTH->value);
        $this->assertSame('all', TimeWindowStrategy::ALL->value);
    }

    /**
     * 测试获取标签功能
     */
    public function testGetLabel(): void
    {
        $this->assertSame('按天', TimeWindowStrategy::DAY->getLabel());
        $this->assertSame('按周', TimeWindowStrategy::WEEK->getLabel());
        $this->assertSame('按月', TimeWindowStrategy::MONTH->getLabel());
        $this->assertSame('全部合并', TimeWindowStrategy::ALL->getLabel());
    }

    /**
     * 测试获取日期格式功能
     */
    public function testGetDateFormat(): void
    {
        $this->assertSame('Y-m-d', TimeWindowStrategy::DAY->getDateFormat());
        $this->assertSame('Y-W', TimeWindowStrategy::WEEK->getDateFormat());
        $this->assertSame('Y-m', TimeWindowStrategy::MONTH->getDateFormat());
        $this->assertSame('', TimeWindowStrategy::ALL->getDateFormat());
    }

    /**
     * 测试从字符串创建枚举实例-正确的输入值
     */
    public function testFromString_validValues(): void
    {
        $this->assertSame(TimeWindowStrategy::DAY, TimeWindowStrategy::fromString('day'));
        $this->assertSame(TimeWindowStrategy::DAY, TimeWindowStrategy::fromString('daily'));
        $this->assertSame(TimeWindowStrategy::WEEK, TimeWindowStrategy::fromString('week'));
        $this->assertSame(TimeWindowStrategy::WEEK, TimeWindowStrategy::fromString('weekly'));
        $this->assertSame(TimeWindowStrategy::MONTH, TimeWindowStrategy::fromString('month'));
        $this->assertSame(TimeWindowStrategy::MONTH, TimeWindowStrategy::fromString('monthly'));
        $this->assertSame(TimeWindowStrategy::ALL, TimeWindowStrategy::fromString('all'));
    }

    /**
     * 测试从字符串创建枚举实例-无效的输入值
     */
    public function testFromString_invalidValue(): void
    {
        $this->assertNull(TimeWindowStrategy::fromString('invalid_value'));
        $this->assertNull(TimeWindowStrategy::fromString(''));
    }

    /**
     * 测试获取所有可选项功能
     */
    public function testGetOptions(): void
    {
        $options = TimeWindowStrategy::getOptions();
        
        $this->assertIsArray($options);
        $this->assertCount(4, $options);
        $this->assertArrayHasKey('day', $options);
        $this->assertArrayHasKey('week', $options);
        $this->assertArrayHasKey('month', $options);
        $this->assertArrayHasKey('all', $options);
        
        $this->assertSame('按天', $options['day']);
        $this->assertSame('按周', $options['week']);
        $this->assertSame('按月', $options['month']);
        $this->assertSame('全部合并', $options['all']);
    }

    /**
     * 测试枚举实现了所需的接口
     */
    public function testEnumImplementsInterfaces(): void
    {
        $reflection = new \ReflectionClass(TimeWindowStrategy::class);
        
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Labelable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Itemable::class));
        $this->assertTrue($reflection->implementsInterface(\Tourze\EnumExtra\Selectable::class));
    }
} 