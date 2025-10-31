<?php

namespace CreditMergeBundle\Service;

use CreditMergeBundle\Enum\TimeWindowStrategy;

/**
 * 时间窗口服务
 * 用于处理时间窗口相关的通用逻辑.
 */
class TimeWindowService
{
    /**
     * 根据过期时间和策略生成时间窗口键.
     *
     * @param \DateTimeInterface $dateTime 日期时间
     * @param TimeWindowStrategy $strategy 策略
     *
     * @return string 窗口键
     */
    public function getTimeWindowKey(\DateTimeInterface $dateTime, TimeWindowStrategy $strategy): string
    {
        return match ($strategy) {
            TimeWindowStrategy::DAY, TimeWindowStrategy::MONTH => $dateTime->format($strategy->getDateFormat()),
            TimeWindowStrategy::WEEK => $dateTime->format('Y').'-W'.$dateTime->format('W'),
            TimeWindowStrategy::ALL => 'all',
        };
    }
}
