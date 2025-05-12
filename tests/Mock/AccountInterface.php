<?php

namespace CreditMergeBundle\Tests\Mock;

use CreditBundle\Entity\Currency;

/**
 * 账户实体接口
 * 仅用于测试，模拟真实的 Account 类
 */
interface AccountInterface
{
    /**
     * 获取ID
     *
     * @return int|null
     */
    public function getId(): ?int;
    
    /**
     * 获取名称
     *
     * @return string
     */
    public function getName(): string;
    
    /**
     * 获取货币
     *
     * @return Currency
     */
    public function getCurrency(): Currency;
}
