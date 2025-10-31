<?php

namespace CreditMergeBundle\Tests\Enum;

use CreditMergeBundle\Enum\TimeWindowStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(TimeWindowStrategy::class)]
final class TimeWindowStrategyTest extends AbstractEnumTestCase
{
    /**
     * 测试获取日期格式功能.
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
    public function testFromStringValidValues(): void
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
    public function testFromStringInvalidValue(): void
    {
        $this->assertNull(TimeWindowStrategy::fromString('invalid_value'));
        $this->assertNull(TimeWindowStrategy::fromString(''));
    }

    /**
     * 测试获取所有可选项功能.
     */
    public function testGetOptions(): void
    {
        $options = TimeWindowStrategy::getOptions();
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
     * 测试toArray方法返回正确的数组格式.
     */
    public function testToArray(): void
    {
        $this->assertEquals(
            ['value' => 'day', 'label' => '按天'],
            TimeWindowStrategy::DAY->toArray()
        );

        $this->assertEquals(
            ['value' => 'week', 'label' => '按周'],
            TimeWindowStrategy::WEEK->toArray()
        );

        $this->assertEquals(
            ['value' => 'month', 'label' => '按月'],
            TimeWindowStrategy::MONTH->toArray()
        );

        $this->assertEquals(
            ['value' => 'all', 'label' => '全部合并'],
            TimeWindowStrategy::ALL->toArray()
        );
    }
}
