<?php

namespace CreditMergeBundle\Tests\Mock;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\Credit;

/**
 * 积分仓库接口
 * 仅用于测试，模拟 Credit 实体的仓库
 */
interface CreditRepositoryInterface
{
    /**
     * 查找账户的小额积分记录
     *
     * @param Account $account 账户
     * @param float $threshold 金额阈值
     * @return Credit[] 小额积分记录数组
     */
    public function findSmallAmountCredits(Account $account, float $threshold): array;
    
    /**
     * 查找指定条件的积分记录
     *
     * @param array $criteria 查询条件
     * @param array|null $orderBy 排序条件
     * @param int|null $limit 限制数量
     * @param int|null $offset 偏移量
     * @return array 积分记录数组
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    
    /**
     * 查找单个积分记录
     *
     * @param array $criteria 查询条件
     * @param array|null $orderBy 排序条件
     * @return Credit|null 积分记录或null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?Credit;
    
    /**
     * 查找指定ID的积分记录
     *
     * @param mixed $id ID
     * @return Credit|null 积分记录或null
     */
    public function find($id): ?Credit;
} 