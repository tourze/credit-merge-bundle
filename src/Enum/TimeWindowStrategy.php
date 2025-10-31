<?php

declare(strict_types=1);

namespace CreditMergeBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 时间窗口策略
 * 用于定义合并小额积分记录时的时间窗口分组策略.
 */
enum TimeWindowStrategy: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;

    /**
     * 按天分组.
     */
    case DAY = 'day';

    /**
     * 按周分组.
     */
    case WEEK = 'week';

    /**
     * 按月分组.
     */
    case MONTH = 'month';

    /**
     * 全部记录作为一组.
     */
    case ALL = 'all';

    /**
     * 获取枚举的展示标签.
     *
     * @return string 展示标签
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DAY => '按天',
            self::WEEK => '按周',
            self::MONTH => '按月',
            self::ALL => '全部合并',
        };
    }

    /**
     * 获取对应时间窗口的日期格式.
     *
     * @return string 日期格式字符串
     */
    public function getDateFormat(): string
    {
        return match ($this) {
            self::DAY => 'Y-m-d',
            self::WEEK => 'Y-W', // 年-周
            self::MONTH => 'Y-m',
            self::ALL => '',
        };
    }

    /**
     * 从字符串创建枚举实例.
     *
     * @param string $value 字符串值
     *
     * @return self|null 枚举实例或null
     */
    public static function fromString(string $value): ?self
    {
        return match ($value) {
            'day', 'daily' => self::DAY,
            'week', 'weekly' => self::WEEK,
            'month', 'monthly' => self::MONTH,
            'all' => self::ALL,
            default => null,
        };
    }

    /**
     * 获取所有可用的选项.
     *
     * @return array<string, string> 选项数组
     */
    public static function getOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    /**
     * 获取徽章颜色.
     *
     * @return string 颜色值
     */
    public function getBadgeColor(): string
    {
        return match ($this) {
            self::DAY => 'success',
            self::WEEK => 'info',
            self::MONTH => 'warning',
            self::ALL => 'danger',
        };
    }

    /**
     * 获取徽章类型.
     *
     * @return string 徽章类型
     */
    public function getBadgeType(): string
    {
        return 'badge';
    }

    /**
     * 获取徽章.
     *
     * @return string 徽章HTML或字符串
     */
    public function getBadge(): string
    {
        return sprintf(
            '<span class="badge badge-%s">%s</span>',
            $this->getBadgeColor(),
            $this->getLabel()
        );
    }
}
