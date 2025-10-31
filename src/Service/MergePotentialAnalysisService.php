<?php

namespace CreditMergeBundle\Service;

/**
 * 积分合并潜力分析服务
 * 负责分析小额积分的合并潜力和推荐合并策略.
 */
class MergePotentialAnalysisService
{
    /**
     * 为分组数据添加合并潜力统计.
     *
     * @param array<string, array<int, array<string, mixed>>> $groupedByWindow
     *
     * @return array<string, array<string, mixed>>
     */
    public function addMergePotentialToGroups(array $groupedByWindow): array
    {
        $result = [];

        foreach ($groupedByWindow as $strategy => $windowGroups) {
            /* @var array<int, array<string, mixed>> $windowGroups */
            $result[$strategy] = [
                'windows' => $windowGroups,
                'window_count' => count($windowGroups),
                'merge_groups' => array_filter($windowGroups, fn ($group) => isset($group['records']) && \is_countable($group['records']) && count($group['records']) > 1),
                'merge_potential' => $this->calculateWindowMergePotential($windowGroups),
            ];
        }

        return $result;
    }

    /**
     * 计算窗口合并潜力.
     *
     * @param array<int, array<string, mixed>> $windowGroups
     *
     * @return array<string, mixed>
     */
    public function calculateWindowMergePotential(array $windowGroups): array
    {
        $potential = [
            'mergeable_windows' => 0,
            'mergeable_records' => 0,
            'mergeable_amount' => 0,
        ];

        foreach ($windowGroups as $window) {
            if (!is_array($window)) {
                continue;
            }

            $records = $window['records'] ?? [];
            $count = isset($window['count']) && is_numeric($window['count']) ? (int) $window['count'] : 0;
            $totalAmount = isset($window['total_amount']) && is_numeric($window['total_amount']) ? (float) $window['total_amount'] : 0.0;

            if (\is_countable($records) && count($records) > 1) {
                ++$potential['mergeable_windows'];
                $potential['mergeable_records'] += $count;
                $potential['mergeable_amount'] += $totalAmount;
            }
        }

        return $potential;
    }

    /**
     * 计算合并潜力.
     */
    /**
     * @param array<string, mixed> $stats
     *
     * @return array<string, mixed>
     */
    public function calculateMergePotential(array $stats): array
    {
        // 安全获取无过期记录数量
        $noExpiryCount = 0;
        $noExpiryAmount = 0;
        if (isset($stats['no_expiry']) && is_array($stats['no_expiry'])) {
            $noExpiryCount = isset($stats['no_expiry']['count']) && is_numeric($stats['no_expiry']['count'])
                ? (int) $stats['no_expiry']['count'] : 0;
            $noExpiryAmount = isset($stats['no_expiry']['amount']) && is_numeric($stats['no_expiry']['amount'])
                ? (float) $stats['no_expiry']['amount'] : 0;
        }

        // 安全获取窗口合并潜力
        $dayPotential = $this->extractMergePotential($stats, 'day');
        $weekPotential = $this->extractMergePotential($stats, 'week');
        $monthPotential = $this->extractMergePotential($stats, 'month');

        return [
            'no_expiry' => [
                'can_merge' => $noExpiryCount > 1,
                'records' => $noExpiryCount > 1 ? $noExpiryCount : 0,
                'amount' => $noExpiryCount > 1 ? $noExpiryAmount : 0,
            ],
            'with_expiry' => [
                'day' => $dayPotential,
                'week' => $weekPotential,
                'month' => $monthPotential,
            ],
            'optimal_strategy' => $this->determineOptimalStrategy($stats),
        ];
    }

    /**
     * 安全提取合并潜力数据.
     *
     * @param array<string, mixed> $stats
     *
     * @return array<string, mixed>
     */
    private function extractMergePotential(array $stats, string $windowType): array
    {
        $defaultPotential = [
            'mergeable_windows' => 0,
            'mergeable_records' => 0,
            'mergeable_amount' => 0,
        ];

        if (!isset($stats['with_expiry']) || !is_array($stats['with_expiry'])) {
            return $defaultPotential;
        }

        /** @var array<string, mixed> $withExpiry */
        $withExpiry = $stats['with_expiry'];
        if (!isset($withExpiry['by_window']) || !is_array($withExpiry['by_window'])) {
            return $defaultPotential;
        }

        /** @var array<string, mixed> $byWindow */
        $byWindow = $withExpiry['by_window'];
        if (!isset($byWindow[$windowType]) || !is_array($byWindow[$windowType])) {
            return $defaultPotential;
        }

        /** @var array<string, mixed> $windowData */
        $windowData = $byWindow[$windowType];
        if (!isset($windowData['merge_potential']) || !is_array($windowData['merge_potential'])) {
            return $defaultPotential;
        }

        /** @var array<string, mixed> $mergePotential */
        $mergePotential = $windowData['merge_potential'];

        return $mergePotential;
    }

    /**
     * 确定最佳合并策略.
     *
     * @param array<string, mixed> $stats
     *
     * @return array<string, mixed>
     */
    public function determineOptimalStrategy(array $stats): array
    {
        $noExpiryStrategy = $this->buildNoExpiryStrategy($stats);
        $windowStrategies = $this->buildWindowStrategies($stats);

        $allStrategies = array_merge([$noExpiryStrategy], $windowStrategies);
        $optimalStrategy = $this->findOptimalStrategy($allStrategies);

        return [
            'strategy' => $optimalStrategy['name'],
            'records' => $optimalStrategy['records'],
            'amount' => $optimalStrategy['amount'],
        ];
    }

    /**
     * 构建无过期策略数据.
     *
     * @param array<string, mixed> $stats
     *
     * @return array<string, mixed>
     */
    private function buildNoExpiryStrategy(array $stats): array
    {
        $noExpiryCount = 0;
        $noExpiryAmount = 0;

        if (isset($stats['no_expiry']) && is_array($stats['no_expiry'])) {
            /** @var array<string, mixed> $noExpiry */
            $noExpiry = $stats['no_expiry'];
            $noExpiryCount = $this->extractNumericValue($noExpiry, 'count', 0);
            $noExpiryAmount = $this->extractNumericValue($noExpiry, 'amount', 0.0);
        }

        return [
            'name' => 'no_expiry',
            'records' => $noExpiryCount > 1 ? $noExpiryCount : 0,
            'amount' => $noExpiryCount > 1 ? $noExpiryAmount : 0,
        ];
    }

    /**
     * 构建窗口策略数据列表.
     *
     * @param array<string, mixed> $stats
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildWindowStrategies(array $stats): array
    {
        $windowTypes = ['day', 'week', 'month'];
        $strategies = [];

        foreach ($windowTypes as $windowType) {
            $potential = $this->extractMergePotential($stats, $windowType);
            $strategies[] = [
                'name' => $windowType,
                'records' => $this->extractNumericValue($potential, 'mergeable_records', 0),
                'amount' => $this->extractNumericValue($potential, 'mergeable_amount', 0.0),
            ];
        }

        return $strategies;
    }

    /**
     * 从数组中安全提取数值.
     *
     * @param array<string, mixed> $data
     */
    private function extractNumericValue(array $data, string $key, int|float $defaultValue): int|float
    {
        if (!isset($data[$key]) || !is_numeric($data[$key])) {
            return $defaultValue;
        }

        return is_float($defaultValue) ? (float) $data[$key] : (int) $data[$key];
    }

    /**
     * 找出最佳策略(按可合并记录数排序).
     *
     * @param array<int, array<string, mixed>> $strategies
     *
     * @return array<string, mixed>
     */
    private function findOptimalStrategy(array $strategies): array
    {
        usort($strategies, function ($a, $b) {
            return $b['records'] <=> $a['records'];
        });

        return $strategies[0];
    }
}
