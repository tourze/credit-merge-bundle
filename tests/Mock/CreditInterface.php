<?php

namespace CreditMergeBundle\Tests\Mock;

/**
 * 积分实体接口
 * 仅用于测试，模拟真实的 Credit 类
 */
interface CreditInterface
{
    /**
     * 获取ID
     *
     * @return int|null
     */
    public function getId(): ?int;
    
    /**
     * 获取金额
     *
     * @return float
     */
    public function getAmount(): float;
    
    /**
     * 获取过期时间
     *
     * @return \DateTimeInterface|null
     */
    public function getExpiryDate(): ?\DateTimeInterface;
} 