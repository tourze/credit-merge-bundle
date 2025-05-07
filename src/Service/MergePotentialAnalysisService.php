<?php

namespace CreditMergeBundle\Service;

/**
 * 积分合并潜力分析服务
 * 负责分析小额积分的合并潜力和推荐合并策略
 */
class MergePotentialAnalysisService
{
    /**
     * 为分组数据添加合并潜力统计
     */
    public function addMergePotentialToGroups(array $groupedByWindow): array
    {
        $result = [];

        foreach ($groupedByWindow as $strategy => $windowGroups) {
            $result[$strategy] = [
                'windows' => $windowGroups,
                'window_count' => count($windowGroups),
                'merge_groups' => array_filter($windowGroups, fn($group) => count($group['records']) > 1),
                'merge_potential' => $this->calculateWindowMergePotential($windowGroups),
            ];
        }

        return $result;
    }

    /**
     * 计算窗口合并潜力
     */
    public function calculateWindowMergePotential(array $windowGroups): array
    {
        $potential = [
            'mergeable_windows' => 0,
            'mergeable_records' => 0,
            'mergeable_amount' => 0,
        ];

        foreach ($windowGroups as $window) {
            if (count($window['records']) > 1) {
                $potential['mergeable_windows']++;
                $potential['mergeable_records'] += $window['count'];
                $potential['mergeable_amount'] += $window['total_amount'];
            }
        }

        return $potential;
    }

    /**
     * 计算合并潜力
     */
    public function calculateMergePotential(array $stats): array
    {
        $mergePotential = [
            'no_expiry' => [
                'can_merge' => $stats['no_expiry']['count'] > 1,
                'records' => $stats['no_expiry']['count'] > 1 ? $stats['no_expiry']['count'] : 0,
                'amount' => $stats['no_expiry']['count'] > 1 ? $stats['no_expiry']['amount'] : 0,
            ],
            'with_expiry' => [
                'day' => $stats['with_expiry']['by_window']['day']['merge_potential'],
                'week' => $stats['with_expiry']['by_window']['week']['merge_potential'],
                'month' => $stats['with_expiry']['by_window']['month']['merge_potential'],
            ],
            'optimal_strategy' => $this->determineOptimalStrategy($stats),
        ];

        return $mergePotential;
    }

    /**
     * 确定最佳合并策略
     */
    public function determineOptimalStrategy(array $stats): array
    {
        // 按可合并记录数量排序策略
        $strategies = [
            'no_expiry' => [
                'name' => 'no_expiry',
                'records' => $stats['no_expiry']['count'] > 1 ? $stats['no_expiry']['count'] : 0,
                'amount' => $stats['no_expiry']['count'] > 1 ? $stats['no_expiry']['amount'] : 0,
            ],
            'day' => [
                'name' => 'day',
                'records' => $stats['with_expiry']['by_window']['day']['merge_potential']['mergeable_records'],
                'amount' => $stats['with_expiry']['by_window']['day']['merge_potential']['mergeable_amount'],
            ],
            'week' => [
                'name' => 'week',
                'records' => $stats['with_expiry']['by_window']['week']['merge_potential']['mergeable_records'],
                'amount' => $stats['with_expiry']['by_window']['week']['merge_potential']['mergeable_amount'],
            ],
            'month' => [
                'name' => 'month',
                'records' => $stats['with_expiry']['by_window']['month']['merge_potential']['mergeable_records'],
                'amount' => $stats['with_expiry']['by_window']['month']['merge_potential']['mergeable_amount'],
            ],
        ];

        // 找出可合并记录数最多的策略
        usort($strategies, function ($a, $b) {
            return $b['records'] <=> $a['records'];
        });

        return [
            'strategy' => $strategies[0]['name'],
            'records' => $strategies[0]['records'],
            'amount' => $strategies[0]['amount'],
        ];
    }
}
