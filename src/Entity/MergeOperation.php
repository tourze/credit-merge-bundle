<?php

declare(strict_types=1);

namespace CreditMergeBundle\Entity;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Repository\MergeOperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 合并操作记录实体
 * 记录每次积分合并操作的详细信息，用于审计和分析.
 */
#[ORM\Entity(repositoryClass: MergeOperationRepository::class)]
#[ORM\Table(
    name: 'credit_merge_operation',
    options: ['comment' => '积分合并操作记录表，记录每次合并操作的详细信息用于审计和分析']
)]
class MergeOperation implements \Stringable
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
        options: ['comment' => '操作执行时间']
    )]
    #[Assert\NotNull(message: '操作时间不能为空')]
    private \DateTimeImmutable $operationTime;

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
        options: ['comment' => '最小合并金额阈值']
    )]
    #[Assert\NotBlank(message: '最小金额阈值不能为空')]
    #[Assert\Positive(message: '最小金额阈值必须为正数')]
    private string $minAmountThreshold;

    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '合并前记录数量']
    )]
    #[Assert\NotNull(message: '合并前记录数量不能为空')]
    #[Assert\PositiveOrZero(message: '合并前记录数量不能为负数')]
    private int $recordsCountBefore;

    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '合并后记录数量']
    )]
    #[Assert\NotNull(message: '合并后记录数量不能为空')]
    #[Assert\PositiveOrZero(message: '合并后记录数量不能为负数')]
    private int $recordsCountAfter;

    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '实际合并的记录数量']
    )]
    #[Assert\NotNull(message: '合并记录数量不能为空')]
    #[Assert\PositiveOrZero(message: '合并记录数量不能为负数')]
    private int $mergedRecordsCount;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 15,
        scale: 2,
        options: ['comment' => '涉及的积分总额']
    )]
    #[Assert\NotBlank(message: '积分总额不能为空')]
    #[Assert\PositiveOrZero(message: '积分总额不能为负数')]
    private string $totalAmount;

    #[ORM\Column(
        type: Types::INTEGER,
        options: ['comment' => '处理批次大小']
    )]
    #[Assert\NotNull(message: '批次大小不能为空')]
    #[Assert\Positive(message: '批次大小必须为正数')]
    private int $batchSize;

    #[ORM\Column(
        type: Types::BOOLEAN,
        options: ['comment' => '是否为模拟运行']
    )]
    #[Assert\NotNull(message: '模拟运行标识不能为空')]
    private bool $isDryRun;

    #[ORM\Column(
        type: Types::STRING,
        length: 20,
        options: ['comment' => '操作结果状态：success, failed, partial']
    )]
    #[Assert\NotBlank(message: '状态不能为空')]
    #[Assert\Choice(
        choices: ['pending', 'running', 'success', 'failed', 'partial'],
        message: '状态必须是有效值'
    )]
    private string $status;

    #[ORM\Column(
        type: Types::TEXT,
        nullable: true,
        options: ['comment' => '操作详细结果或错误信息']
    )]
    #[Assert\Length(max: 65535, maxMessage: '结果信息长度不能超过65535个字符')]
    private ?string $resultMessage = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(
        type: Types::JSON,
        nullable: true,
        options: ['comment' => '扩展上下文信息（JSON格式）']
    )]
    #[Assert\Type(type: 'array', message: '上下文信息必须是数组类型')]
    private ?array $context = null;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 8,
        scale: 3,
        nullable: true,
        options: ['comment' => '执行耗时（秒）']
    )]
    #[Assert\PositiveOrZero(message: '执行时间不能为负数')]
    private ?string $executionTime = null;

    #[ORM\Column(
        type: Types::DATETIME_IMMUTABLE,
        options: ['comment' => '记录创建时间']
    )]
    #[Assert\NotNull(message: '创建时间不能为空')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->operationTime = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'pending';
        $this->isDryRun = false;
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

    public function getOperationTime(): \DateTimeImmutable
    {
        return $this->operationTime;
    }

    public function setOperationTime(\DateTimeImmutable $operationTime): void
    {
        $this->operationTime = $operationTime;
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

    public function getRecordsCountBefore(): int
    {
        return $this->recordsCountBefore;
    }

    public function setRecordsCountBefore(int $recordsCountBefore): void
    {
        $this->recordsCountBefore = $recordsCountBefore;
    }

    public function getRecordsCountAfter(): int
    {
        return $this->recordsCountAfter;
    }

    public function setRecordsCountAfter(int $recordsCountAfter): void
    {
        $this->recordsCountAfter = $recordsCountAfter;
    }

    public function getMergedRecordsCount(): int
    {
        return $this->mergedRecordsCount;
    }

    public function setMergedRecordsCount(int $mergedRecordsCount): void
    {
        $this->mergedRecordsCount = $mergedRecordsCount;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    public function isDryRun(): bool
    {
        return $this->isDryRun;
    }

    public function setIsDryRun(bool $isDryRun): void
    {
        $this->isDryRun = $isDryRun;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getResultMessage(): ?string
    {
        return $this->resultMessage;
    }

    public function setResultMessage(?string $resultMessage): void
    {
        $this->resultMessage = $resultMessage;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function setContext(?array $context): void
    {
        $this->context = $context;
    }

    public function getExecutionTime(): ?string
    {
        return $this->executionTime;
    }

    public function setExecutionTime(?string $executionTime): void
    {
        $this->executionTime = $executionTime;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return sprintf(
            'MergeOperation #%d [%s] %s - %d/%d records',
            $this->id ?? 0,
            $this->status,
            $this->timeWindowStrategy->value,
            $this->mergedRecordsCount,
            $this->recordsCountBefore
        );
    }
}
