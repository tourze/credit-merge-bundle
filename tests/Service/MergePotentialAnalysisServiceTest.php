<?php

namespace CreditMergeBundle\Tests\Service;

use CreditMergeBundle\Service\MergePotentialAnalysisService;
use PHPUnit\Framework\TestCase;

class MergePotentialAnalysisServiceTest extends TestCase
{
    private MergePotentialAnalysisService $service;

    protected function setUp(): void
    {
        $this->service = new MergePotentialAnalysisService();
    }

    public function testAddMergePotentialToGroups(): void
    {
        $groupedByWindow = [
            'monthly' => [
                '2024-01' => [
                    'records' => [1, 2, 3],
                    'count' => 3,
                    'total_amount' => 30.0,
                ],
                '2024-02' => [
                    'records' => [4],
                    'count' => 1,
                    'total_amount' => 10.0,
                ],
                '2024-03' => [
                    'records' => [5, 6],
                    'count' => 2,
                    'total_amount' => 20.0,
                ],
            ],
            'weekly' => [
                '2024-W01' => [
                    'records' => [1, 2],
                    'count' => 2,
                    'total_amount' => 15.0,
                ],
                '2024-W02' => [
                    'records' => [3],
                    'count' => 1,
                    'total_amount' => 5.0,
                ],
            ],
        ];

        $result = $this->service->addMergePotentialToGroups($groupedByWindow);

        // 验证月度策略
        $this->assertArrayHasKey('monthly', $result);
        $this->assertEquals(3, $result['monthly']['window_count']);
        $this->assertCount(2, $result['monthly']['merge_groups']); // 只有2个窗口有多条记录
        $this->assertEquals(2, $result['monthly']['merge_potential']['mergeable_windows']);
        $this->assertEquals(5, $result['monthly']['merge_potential']['mergeable_records']); // 3 + 2
        $this->assertEquals(50.0, $result['monthly']['merge_potential']['mergeable_amount']); // 30 + 20

        // 验证周策略
        $this->assertArrayHasKey('weekly', $result);
        $this->assertEquals(2, $result['weekly']['window_count']);
        $this->assertCount(1, $result['weekly']['merge_groups']); // 只有1个窗口有多条记录
        $this->assertEquals(1, $result['weekly']['merge_potential']['mergeable_windows']);
        $this->assertEquals(2, $result['weekly']['merge_potential']['mergeable_records']);
        $this->assertEquals(15.0, $result['weekly']['merge_potential']['mergeable_amount']);
    }

    public function testCalculateWindowMergePotential(): void
    {
        $windowGroups = [
            '2024-01' => [
                'records' => [1, 2, 3],
                'count' => 3,
                'total_amount' => 30.0,
            ],
            '2024-02' => [
                'records' => [4],
                'count' => 1,
                'total_amount' => 10.0,
            ],
            '2024-03' => [
                'records' => [5, 6],
                'count' => 2,
                'total_amount' => 20.0,
            ],
        ];

        $potential = $this->service->calculateWindowMergePotential($windowGroups);

        $this->assertEquals(2, $potential['mergeable_windows']);
        $this->assertEquals(5, $potential['mergeable_records']);
        $this->assertEquals(50.0, $potential['mergeable_amount']);
    }

    public function testCalculateMergePotential(): void
    {
        $stats = [
            'no_expiry' => [
                'count' => 5,
                'amount' => 25.0,
            ],
            'with_expiry' => [
                'by_window' => [
                    'day' => [
                        'merge_potential' => [
                            'mergeable_windows' => 3,
                            'mergeable_records' => 10,
                            'mergeable_amount' => 50.0,
                        ],
                    ],
                    'week' => [
                        'merge_potential' => [
                            'mergeable_windows' => 2,
                            'mergeable_records' => 8,
                            'mergeable_amount' => 40.0,
                        ],
                    ],
                    'month' => [
                        'merge_potential' => [
                            'mergeable_windows' => 1,
                            'mergeable_records' => 3,
                            'mergeable_amount' => 15.0,
                        ],
                    ],
                ],
            ],
        ];

        $potential = $this->service->calculateMergePotential($stats);

        // 验证无过期时间的合并潜力
        $this->assertTrue($potential['no_expiry']['can_merge']);
        $this->assertEquals(5, $potential['no_expiry']['records']);
        $this->assertEquals(25.0, $potential['no_expiry']['amount']);

        // 验证有过期时间的合并潜力
        $this->assertEquals(10, $potential['with_expiry']['day']['mergeable_records']);
        $this->assertEquals(8, $potential['with_expiry']['week']['mergeable_records']);
        $this->assertEquals(3, $potential['with_expiry']['month']['mergeable_records']);

        // 验证最佳策略
        $this->assertEquals('day', $potential['optimal_strategy']['strategy']);
        $this->assertEquals(10, $potential['optimal_strategy']['records']);
        $this->assertEquals(50.0, $potential['optimal_strategy']['amount']);
    }

    public function testCalculateMergePotentialWithNoMergeableNoExpiry(): void
    {
        $stats = [
            'no_expiry' => [
                'count' => 1, // 只有1条记录，无法合并
                'amount' => 5.0,
            ],
            'with_expiry' => [
                'by_window' => [
                    'day' => [
                        'merge_potential' => [
                            'mergeable_windows' => 0,
                            'mergeable_records' => 0,
                            'mergeable_amount' => 0,
                        ],
                    ],
                    'week' => [
                        'merge_potential' => [
                            'mergeable_windows' => 1,
                            'mergeable_records' => 2,
                            'mergeable_amount' => 10.0,
                        ],
                    ],
                    'month' => [
                        'merge_potential' => [
                            'mergeable_windows' => 0,
                            'mergeable_records' => 0,
                            'mergeable_amount' => 0,
                        ],
                    ],
                ],
            ],
        ];

        $potential = $this->service->calculateMergePotential($stats);

        // 验证无过期时间的合并潜力
        $this->assertFalse($potential['no_expiry']['can_merge']);
        $this->assertEquals(0, $potential['no_expiry']['records']);
        $this->assertEquals(0, $potential['no_expiry']['amount']);

        // 验证最佳策略为week
        $this->assertEquals('week', $potential['optimal_strategy']['strategy']);
        $this->assertEquals(2, $potential['optimal_strategy']['records']);
        $this->assertEquals(10.0, $potential['optimal_strategy']['amount']);
    }

    public function testDetermineOptimalStrategy(): void
    {
        $stats = [
            'no_expiry' => [
                'count' => 3,
                'amount' => 15.0,
            ],
            'with_expiry' => [
                'by_window' => [
                    'day' => [
                        'merge_potential' => [
                            'mergeable_records' => 5,
                            'mergeable_amount' => 25.0,
                        ],
                    ],
                    'week' => [
                        'merge_potential' => [
                            'mergeable_records' => 8,
                            'mergeable_amount' => 40.0,
                        ],
                    ],
                    'month' => [
                        'merge_potential' => [
                            'mergeable_records' => 2,
                            'mergeable_amount' => 10.0,
                        ],
                    ],
                ],
            ],
        ];

        $optimal = $this->service->determineOptimalStrategy($stats);

        $this->assertEquals('week', $optimal['strategy']);
        $this->assertEquals(8, $optimal['records']);
        $this->assertEquals(40.0, $optimal['amount']);
    }
}