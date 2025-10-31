<?php

declare(strict_types=1);

namespace CreditMergeBundle\Entity;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Repository\MergeStatisticsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 合并统计历史实体
 * 记录合并操作的统计数据，用于分析和展示.
 */
#[ORM\Entity(repositoryClass: MergeStatisticsRepository::class)]
#[ORM\Table(
    name: 'credit_merge_statistics',
    options: ['comment' => '积分合并统计历史表，记录合并操作的统计数据用于分析和展示']
)]
class MergeStatistics implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '主键ID']
    )]
    private ?int $id = null;

    /**
     * 关联的账户.
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(
        name: 'account_id',
        referencedColumnName: 'id',
        nullable: false,
        options: ['comment' => '关联账户ID']
    )]
    #[Assert\NotNull(message: '账户不能为空')]
    private Account $account;

    #[ORM\Column(
        type: Types::DATETIME_IMMUTABLE,
        options: ['comment' => '统计数据生成时间']
    )]
    #[Assert\NotNull(message: '统计时间不能为空')]
    private \DateTimeImmutable $statisticsTime;

    #[ORM\Column(
        type: Types::STRING,
        enumType: TimeWindowStrategy::class,
        length: 20,
        options: ['comment' => '时间窗口策略类型']
    )]
    #[Assert\NotNull(message: '时间窗口策略不能为空')]
    #[Assert\Choice(
        choices: ['day', 'week', 'month', 'all'],
        message: '时间窗口策略必须是有效值'
    )]
    private TimeWindowStrategy $timeWindowStrategy;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        options: ['comment' => '使用的最小金额阈值']
    )]
    #[Assert\NotBlank(message: '最小金额阈值不能为空')]
    #[Assert\Positive(message: '最小金额阈值必须为正数')]
    private string $minAmountThreshold;

    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '小额记录总数量']
    )]
    #[Assert\NotNull(message: '小额记录数量不能为空')]
    #[Assert\PositiveOrZero(message: '小额记录数量不能为负数')]
    private int $totalSmallRecords;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 15,
        scale: 2,
        options: ['comment' => '小额积分总额']
    )]
    #[Assert\NotBlank(message: '小额积分总额不能为空')]
    #[Assert\PositiveOrZero(message: '小额积分总额不能为负数')]
    private string $totalSmallAmount;

    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '可合并的记录数量']
    )]
    #[Assert\NotNull(message: '可合并记录数量不能为空')]
    #[Assert\PositiveOrZero(message: '可合并记录数量不能为负数')]
    private int $mergeableRecords;

    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '潜在减少的记录数量']
    )]
    #[Assert\NotNull(message: '潜在减少记录数量不能为空')]
    #[Assert\PositiveOrZero(message: '潜在减少记录数量不能为负数')]
    private int $potentialRecordReduction;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 5,
        scale: 2,
        options: ['comment' => '合并效率百分比']
    )]
    #[Assert\NotBlank(message: '合并效率不能为空')]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: '合并效率必须在0-100之间'
    )]
    private string $mergeEfficiency;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        options: ['comment' => '平均每条记录金额']
    )]
    #[Assert\NotBlank(message: '平均金额不能为空')]
    #[Assert\PositiveOrZero(message: '平均金额不能为负数')]
    private string $averageAmount;

    /**
     * @var array<int|string, mixed>|null
     */
    #[ORM\Column(
        type: Types::JSON,
        nullable: true,
        options: ['comment' => '分组统计详情（JSON格式）']
    )]
    #[Assert\Type(type: 'array', message: '分组统计信息必须是数组类型')]
    private ?array $groupStats = null;

    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '时间窗口分组数量']
    )]
    #[Assert\NotNull(message: '时间窗口分组数量不能为空')]
    #[Assert\PositiveOrZero(message: '时间窗口分组数量不能为负数')]
    private int $timeWindowGroups;

    /**
     * @var array<int|string, mixed>|null
     */
    #[ORM\Column(
        type: Types::JSON,
        nullable: true,
        options: ['comment' => '统计上下文信息（JSON格式）']
    )]
    #[Assert\Type(type: 'array', message: '上下文信息必须是数组类型')]
    private ?array $context = null;

    #[ORM\Column(
        type: Types::DATETIME_IMMUTABLE,
        options: ['comment' => '记录创建时间']
    )]
    #[Assert\NotNull(message: '创建时间不能为空')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->statisticsTime = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->totalSmallRecords = 0;
        $this->mergeableRecords = 0;
        $this->potentialRecordReduction = 0;
        $this->timeWindowGroups = 0;
        $this->totalSmallAmount = '0.00';
        $this->mergeEfficiency = '0.00';
        $this->averageAmount = '0.00';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }

    public function getStatisticsTime(): \DateTimeImmutable
    {
        return $this->statisticsTime;
    }

    public function setStatisticsTime(\DateTimeImmutable $statisticsTime): void
    {
        $this->statisticsTime = $statisticsTime;
    }

    public function getTimeWindowStrategy(): TimeWindowStrategy
    {
        return $this->timeWindowStrategy;
    }

    public function setTimeWindowStrategy(TimeWindowStrategy $timeWindowStrategy): void
    {
        $this->timeWindowStrategy = $timeWindowStrategy;
    }

    public function getMinAmountThreshold(): string
    {
        return $this->minAmountThreshold;
    }

    public function setMinAmountThreshold(string $minAmountThreshold): void
    {
        $this->minAmountThreshold = $minAmountThreshold;
    }

    public function getTotalSmallRecords(): int
    {
        return $this->totalSmallRecords;
    }

    public function setTotalSmallRecords(int $totalSmallRecords): void
    {
        $this->totalSmallRecords = $totalSmallRecords;
    }

    public function getTotalSmallAmount(): string
    {
        return $this->totalSmallAmount;
    }

    public function setTotalSmallAmount(string $totalSmallAmount): void
    {
        $this->totalSmallAmount = $totalSmallAmount;
    }

    public function getMergeableRecords(): int
    {
        return $this->mergeableRecords;
    }

    public function setMergeableRecords(int $mergeableRecords): void
    {
        $this->mergeableRecords = $mergeableRecords;
    }

    public function getPotentialRecordReduction(): int
    {
        return $this->potentialRecordReduction;
    }

    public function setPotentialRecordReduction(int $potentialRecordReduction): void
    {
        $this->potentialRecordReduction = $potentialRecordReduction;
    }

    public function getMergeEfficiency(): string
    {
        return $this->mergeEfficiency;
    }

    public function setMergeEfficiency(string $mergeEfficiency): void
    {
        $this->mergeEfficiency = $mergeEfficiency;
    }

    public function getAverageAmount(): string
    {
        return $this->averageAmount;
    }

    public function setAverageAmount(string $averageAmount): void
    {
        $this->averageAmount = $averageAmount;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getGroupStats(): ?array
    {
        return $this->groupStats;
    }

    /**
     * @param array<int|string, mixed>|null $groupStats
     */
    public function setGroupStats(?array $groupStats): void
    {
        $this->groupStats = $groupStats;
    }

    public function getTimeWindowGroups(): int
    {
        return $this->timeWindowGroups;
    }

    public function setTimeWindowGroups(int $timeWindowGroups): void
    {
        $this->timeWindowGroups = $timeWindowGroups;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<int|string, mixed>|null $context
     */
    public function setContext(?array $context): void
    {
        $this->context = $context;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return sprintf(
            'MergeStatistics #%d [%s] %s records, %s%% efficiency',
            $this->id ?? 0,
            $this->timeWindowStrategy->value,
            $this->totalSmallRecords,
            $this->mergeEfficiency
        );
    }
}
