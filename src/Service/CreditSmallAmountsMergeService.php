<?php

namespace CreditMergeBundle\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Repository\TransactionRepository;
use Psr\Log\LoggerInterface;

/**
 * 小额积分合并服务
 * 负责处理扣减积分前的小额积分合并逻辑
 */
class CreditSmallAmountsMergeService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 检查并在需要时合并小额积分
     */
    public function checkAndMergeIfNeeded(Account $account, float $costAmount): void
    {
        $autoMergeEnabled = !isset($_ENV['CREDIT_AUTO_MERGE_ENABLED']) || $_ENV['CREDIT_AUTO_MERGE_ENABLED'];
        $autoMergeThreshold = isset($_ENV['CREDIT_AUTO_MERGE_THRESHOLD']) ? (int)$_ENV['CREDIT_AUTO_MERGE_THRESHOLD'] : 100;
        $autoMergeMinAmount = isset($_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT']) ? (float)$_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] : 100.0;
        $timeWindowStrategy = $_ENV['CREDIT_TIME_WINDOW_STRATEGY'] ?? 'monthly';

        if (!$this->shouldMerge($autoMergeEnabled, $costAmount, $autoMergeMinAmount)) {
            return;
        }

        $preview = $this->transactionRepository->getConsumptionPreview($account, $costAmount, $autoMergeThreshold);

        if (!$preview->needsMerge()) {
            return;
        }

        $this->logMergeStart($account, $costAmount, $preview->getRecordCount(), $autoMergeThreshold, $timeWindowStrategy);

        // 执行小额积分合并
        $mergeCount = $this->executeMergeSmallAmounts($account, $timeWindowStrategy);

        $this->logMergeComplete($account, $mergeCount, $timeWindowStrategy);
    }

    /**
     * 判断是否需要合并小额积分
     */
    private function shouldMerge(bool $autoMergeEnabled, float $costAmount, float $autoMergeMinAmount): bool
    {
        return $autoMergeEnabled && $costAmount >= $autoMergeMinAmount;
    }

    /**
     * 执行小额积分合并
     */
    private function executeMergeSmallAmounts(Account $account, string $timeWindowStrategy): int
    {
        $minAmountToMerge = isset($_ENV['CREDIT_MIN_AMOUNT_TO_MERGE']) ? (float)$_ENV['CREDIT_MIN_AMOUNT_TO_MERGE'] : 5.0;
        return $this->transactionRepository->mergeSmallAmounts(
            $account,
            $minAmountToMerge,
            100, // 批量大小固定为100
            $timeWindowStrategy
        );
    }

    /**
     * 记录合并开始日志
     */
    private function logMergeStart(Account $account, float $costAmount, int $recordCount, int $threshold, string $strategy): void
    {
        $this->logger->info('大额消费触发小额积分合并', [
            'account' => $account->getId(),
            'costAmount' => $costAmount,
            'recordCount' => $recordCount,
            'threshold' => $threshold,
            'strategy' => $strategy
        ]);
    }

    /**
     * 记录合并完成日志
     */
    private function logMergeComplete(Account $account, int $mergeCount, string $strategy): void
    {
        $this->logger->info('小额积分合并完成', [
            'account' => $account->getId(),
            'mergeCount' => $mergeCount,
            'strategy' => $strategy
        ]);
    }
}
