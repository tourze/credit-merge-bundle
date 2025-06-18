<?php

namespace CreditMergeBundle\Model;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;

/**
 * 小额积分统计信息模型
 */
class SmallAmountStats implements \JsonSerializable
{
    /**
     * @var Account 账户
     */
    private Account $account;

    /**
     * @var int 小额积分记录数量
     */
    private int $count;

    /**
     * @var float 小额积分总额
     */
    private float $total;

    /**
     * @var float 小额积分阈值
     */
    private float $threshold;

    /**
     * @var ?TimeWindowStrategy 时间窗口策略
     */
    private ?TimeWindowStrategy $strategy = null;

    /**
     * @var array 分组统计信息
     */
    private array $groupStats = [];

    /**
     * @param Account $account 账户
     * @param int $count 记录数量
     * @param float $total 积分总额
     * @param float $threshold 积分阈值
     * @param ?TimeWindowStrategy $strategy 时间窗口策略
     */
    public function __construct(
        Account $account,
        int $count,
        float $total,
        float $threshold,
        ?TimeWindowStrategy $strategy = null
    ) {
        $this->account = $account;
        $this->count = $count;
        $this->total = $total;
        $this->threshold = $threshold;
        $this->strategy = $strategy;
    }

    /**
     * 获取账户
     *
     * @return Account
     */
    public function getAccount(): Account
    {
        return $this->account;
    }

    /**
     * 获取小额记录数量
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * 获取小额积分总额
     *
     * @return float
     */
    public function getTotal(): float
    {
        return $this->total;
    }

    /**
     * 获取小额积分阈值
     *
     * @return float
     */
    public function getThreshold(): float
    {
        return $this->threshold;
    }

    /**
     * 获取时间窗口策略
     *
     * @return TimeWindowStrategy|null
     */
    public function getStrategy(): ?TimeWindowStrategy
    {
        return $this->strategy;
    }

    /**
     * 设置时间窗口策略
     *
     * @param TimeWindowStrategy $strategy 策略枚举
     * @return self
     */
    public function setStrategy(TimeWindowStrategy $strategy): self
    {
        $this->strategy = $strategy;
        return $this;
    }

    /**
     * 添加分组统计信息
     *
     * @param string $groupKey 分组键
     * @param int $count 该组记录数
     * @param float $total 该组积分总额
     * @param \DateTimeInterface|null $earliestExpiry 该组最早过期时间
     * @return self
     */
    public function addGroupStats(
        string $groupKey,
        int $count,
        float $total,
        ?\DateTimeInterface $earliestExpiry = null
    ): self {
        $this->groupStats[$groupKey] = [
            'count' => $count,
            'total' => $total,
            'earliest_expiry' => $earliestExpiry !== null ? $earliestExpiry->format('Y-m-d H:i:s') : null
        ];

        return $this;
    }

    /**
     * 获取分组统计信息
     *
     * @return array
     */
    public function getGroupStats(): array
    {
        return $this->groupStats;
    }

    /**
     * 判断是否有可合并的积分
     *
     * @return bool
     */
    public function hasMergeableRecords(): bool
    {
        return $this->count > 1;
    }

    /**
     * 计算合并后可能减少的记录数
     *
     * @return int
     */
    public function getPotentialRecordReduction(): int
    {
        if (!$this->hasMergeableRecords()) {
            return 0;
        }

        if (empty($this->groupStats)) {
            // 如果没有分组统计，假设所有记录合并为一条
            return $this->count - 1;
        }

        // 计算分组后的减少数量（每组减少的记录数量 = 记录数 - 1）
        $reduction = 0;
        foreach ($this->groupStats as $stats) {
            if ($stats['count'] > 1) {
                $reduction += $stats['count'] - 1;
            }
        }

        return $reduction;
    }

    /**
     * 获取合并效率（减少的记录数占原记录数的百分比）
     *
     * @return float 百分比（0-100）
     */
    public function getMergeEfficiency(): float
    {
        if ($this->count <= 1) {
            return 0.0;
        }

        return (float)($this->getPotentialRecordReduction() / $this->count * 100);
    }

    /**
     * 平均每条记录的金额
     *
     * @return float
     */
    public function getAverageAmount(): float
    {
        if ($this->count === 0) {
            return 0.0;
        }

        return $this->total / $this->count;
    }

    /**
     * 转换为JSON时的数据
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'account_id' => $this->account->getId(),
            'count' => $this->count,
            'total' => $this->total,
            'threshold' => $this->threshold,
            'strategy' => $this->strategy?->value,
            'average_amount' => $this->getAverageAmount(),
            'has_mergeable_records' => $this->hasMergeableRecords(),
            'potential_reduction' => $this->getPotentialRecordReduction(),
            'merge_efficiency' => $this->getMergeEfficiency(),
            'group_stats' => $this->groupStats,
        ];
    }

    /**
     * 创建一个空的统计对象
     *
     * @param Account $account
     * @param float $threshold
     * @return self
     */
    public static function createEmpty(Account $account, float $threshold = 5.0): self
    {
        return new self($account, 0, 0.0, $threshold);
    }
}
