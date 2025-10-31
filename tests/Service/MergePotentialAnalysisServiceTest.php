<?php

namespace CreditMergeBundle\Tests\Service;

use CreditMergeBundle\Service\MergePotentialAnalysisService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MergePotentialAnalysisService::class)]
#[RunTestsInSeparateProcesses]
final class MergePotentialAnalysisServiceTest extends AbstractIntegrationTestCase
{
    private MergePotentialAnalysisService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(MergePotentialAnalysisService::class);
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(MergePotentialAnalysisService::class, $this->service);
    }

    /**
     * 测试为分组数据添加合并潜力统计 - 成功场景.
     */
    /**
     * @param array<string, array<int, array<string, mixed>>> $inputGroupedByWindow
     * @param array<int, int>                                 $expectedWindowCounts
     * @param array<int, int>                                 $expectedMergeGroupCounts
     */
    #[DataProvider('addMergePotentialToGroupsDataProvider')]
    public function testAddMergePotentialToGroups(
        array $inputGroupedByWindow,
        int $expectedStrategies,
        array $expectedWindowCounts,
        array $expectedMergeGroupCounts,
    ): void {
        $result = $this->service->addMergePotentialToGroups($inputGroupedByWindow);

        // 验证返回的策略数量
        $this->assertCount($expectedStrategies, $result);

        // 验证每个策略的结构
        $strategyIndex = 0;
        foreach ($result as $strategy => $data) {
            $this->assertArrayHasKey('windows', $data);
            $this->assertArrayHasKey('window_count', $data);
            $this->assertArrayHasKey('merge_groups', $data);
            $this->assertArrayHasKey('merge_potential', $data);

            // 验证窗口数量
            $this->assertIsArray($data);
            $this->assertSame($expectedWindowCounts[$strategyIndex], $data['window_count']);

            // 验证可合并组数量
            $this->assertIsArray($data['merge_groups']);
            $this->assertCount($expectedMergeGroupCounts[$strategyIndex], $data['merge_groups']);

            // 验证合并潜力结构
            $this->assertIsArray($data['merge_potential']);
            $this->assertArrayHasKey('mergeable_windows', $data['merge_potential']);
            $this->assertArrayHasKey('mergeable_records', $data['merge_potential']);
            $this->assertArrayHasKey('mergeable_amount', $data['merge_potential']);

            ++$strategyIndex;
        }
    }

    /**
     * 测试为分组数据添加合并潜力统计 - 空数据场景.
     */
    public function testAddMergePotentialToGroupsEmptyData(): void
    {
        $result = $this->service->addMergePotentialToGroups([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * 测试计算窗口合并潜力 - 成功场景.
     */
    /**
     * @param array<int, array<string, mixed>> $windowGroups
     */
    #[DataProvider('calculateWindowMergePotentialDataProvider')]
    public function testCalculateWindowMergePotential(
        array $windowGroups,
        int $expectedMergeableWindows,
        int $expectedMergeableRecords,
        float $expectedMergeableAmount,
    ): void {
        $result = $this->service->calculateWindowMergePotential($windowGroups);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('mergeable_windows', $result);
        $this->assertArrayHasKey('mergeable_records', $result);
        $this->assertArrayHasKey('mergeable_amount', $result);

        $this->assertSame($expectedMergeableWindows, $result['mergeable_windows']);
        $this->assertSame($expectedMergeableRecords, $result['mergeable_records']);
        $this->assertEquals($expectedMergeableAmount, $result['mergeable_amount']);
    }

    /**
     * 测试计算窗口合并潜力 - 空数据场景.
     */
    public function testCalculateWindowMergePotentialEmptyData(): void
    {
        $result = $this->service->calculateWindowMergePotential([]);

        $expected = [
            'mergeable_windows' => 0,
            'mergeable_records' => 0,
            'mergeable_amount' => 0,
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * 测试计算窗口合并潜力 - 单记录场景（不可合并）.
     */
    public function testCalculateWindowMergePotentialSingleRecords(): void
    {
        $windowGroups = [
            ['records' => [1], 'count' => 1, 'total_amount' => 5.0],
            ['records' => [2], 'count' => 1, 'total_amount' => 3.0],
            ['records' => [3], 'count' => 1, 'total_amount' => 4.0],
        ];

        $result = $this->service->calculateWindowMergePotential($windowGroups);

        $expected = [
            'mergeable_windows' => 0,
            'mergeable_records' => 0,
            'mergeable_amount' => 0,
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * 测试计算合并潜力 - 完整场景.
     */
    /**
     * @param array<string, mixed> $inputStats
     */
    #[DataProvider('calculateMergePotentialDataProvider')]
    public function testCalculateMergePotential(
        array $inputStats,
        bool $expectedNoExpiryCanMerge,
        int $expectedNoExpiryRecords,
        float $expectedNoExpiryAmount,
        string $expectedOptimalStrategy,
    ): void {
        $result = $this->service->calculateMergePotential($inputStats);

        // 验证返回结构
        $this->assertArrayHasKey('no_expiry', $result);
        $this->assertArrayHasKey('with_expiry', $result);
        $this->assertArrayHasKey('optimal_strategy', $result);

        // 验证无过期记录潜力
        $this->assertIsArray($result['no_expiry']);
        /** @var array<string, mixed> $noExpiryPotential */
        $noExpiryPotential = $result['no_expiry'];
        $this->assertSame($expectedNoExpiryCanMerge, $noExpiryPotential['can_merge']);
        $this->assertSame($expectedNoExpiryRecords, $noExpiryPotential['records']);
        $this->assertEquals($expectedNoExpiryAmount, $noExpiryPotential['amount']);

        // 验证有过期记录潜力结构
        $this->assertIsArray($result['with_expiry']);
        /** @var array<string, mixed> $withExpiryPotential */
        $withExpiryPotential = $result['with_expiry'];
        $this->assertArrayHasKey('day', $withExpiryPotential);
        $this->assertArrayHasKey('week', $withExpiryPotential);
        $this->assertArrayHasKey('month', $withExpiryPotential);

        // 验证最佳策略
        $this->assertIsArray($result['optimal_strategy']);
        /** @var array<string, mixed> $optimalStrategy */
        $optimalStrategy = $result['optimal_strategy'];
        $this->assertArrayHasKey('strategy', $optimalStrategy);
        $this->assertArrayHasKey('records', $optimalStrategy);
        $this->assertArrayHasKey('amount', $optimalStrategy);
        $this->assertSame($expectedOptimalStrategy, $optimalStrategy['strategy']);
    }

    /**
     * 测试确定最佳合并策略 - 成功场景.
     */
    /**
     * @param array<string, mixed> $inputStats
     */
    #[DataProvider('determineOptimalStrategyDataProvider')]
    public function testDetermineOptimalStrategy(
        array $inputStats,
        string $expectedStrategy,
        int $expectedRecords,
        float $expectedAmount,
    ): void {
        $result = $this->service->determineOptimalStrategy($inputStats);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('amount', $result);

        $this->assertSame($expectedStrategy, $result['strategy']);
        $this->assertSame($expectedRecords, $result['records']);
        $this->assertEquals($expectedAmount, $result['amount']);
    }

    /**
     * 测试确定最佳合并策略 - 所有策略记录数相同.
     */
    public function testDetermineOptimalStrategyTieBreaking(): void
    {
        $stats = [
            'no_expiry' => ['count' => 5, 'amount' => 25.0],
            'with_expiry' => [
                'by_window' => [
                    'day' => ['merge_potential' => ['mergeable_records' => 5, 'mergeable_amount' => 20.0]],
                    'week' => ['merge_potential' => ['mergeable_records' => 5, 'mergeable_amount' => 30.0]],
                    'month' => ['merge_potential' => ['mergeable_records' => 5, 'mergeable_amount' => 15.0]],
                ],
            ],
        ];

        $result = $this->service->determineOptimalStrategy($stats);

        // 当记录数相同时，应该返回第一个遇到的策略（按数组顺序）
        $this->assertSame('no_expiry', $result['strategy']);
        $this->assertSame(5, $result['records']);
        $this->assertEquals(25.0, $result['amount']);
    }

    /**
     * 测试确定最佳合并策略 - 零记录场景.
     */
    public function testDetermineOptimalStrategyZeroRecords(): void
    {
        $stats = [
            'no_expiry' => ['count' => 0, 'amount' => 0.0],
            'with_expiry' => [
                'by_window' => [
                    'day' => ['merge_potential' => ['mergeable_records' => 0, 'mergeable_amount' => 0]],
                    'week' => ['merge_potential' => ['mergeable_records' => 0, 'mergeable_amount' => 0]],
                    'month' => ['merge_potential' => ['mergeable_records' => 0, 'mergeable_amount' => 0]],
                ],
            ],
        ];

        $result = $this->service->determineOptimalStrategy($stats);

        $this->assertSame('no_expiry', $result['strategy']);
        $this->assertSame(0, $result['records']);
        $this->assertSame(0, $result['amount']);
    }

    /**
     * 测试数据结构的完整性和一致性.
     */
    public function testDataStructureIntegrity(): void
    {
        $groupedByWindow = [
            'month' => [
                [
                    'records' => [1, 2, 3],
                    'count' => 3,
                    'total_amount' => 15.0,
                ],
                [
                    'records' => [4],
                    'count' => 1,
                    'total_amount' => 5.0,
                ],
            ],
            'week' => [
                [
                    'records' => [5, 6],
                    'count' => 2,
                    'total_amount' => 10.0,
                ],
            ],
        ];

        $result = $this->service->addMergePotentialToGroups($groupedByWindow);

        // 验证每个策略的数据完整性
        foreach ($result as $strategy => $data) {
            $this->assertContains($strategy, ['month', 'week']);

            // 验证 windows 数据与原始数据一致
            $this->assertSame($groupedByWindow[$strategy], $data['windows']);

            // 验证 window_count 计算正确
            $this->assertSame(count($groupedByWindow[$strategy]), $data['window_count']);

            // 验证 merge_groups 过滤逻辑正确
            $expectedMergeGroups = array_filter(
                $groupedByWindow[$strategy],
                fn ($group) => count($group['records']) > 1
            );
            $this->assertSame($expectedMergeGroups, $data['merge_groups']);
        }
    }

    // ============= DataProvider 方法 =============

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function addMergePotentialToGroupsDataProvider(): array
    {
        return [
            'single_strategy_with_mergeable_groups' => [
                [
                    'month' => [
                        ['records' => [1, 2, 3], 'count' => 3, 'total_amount' => 15.0],
                        ['records' => [4, 5], 'count' => 2, 'total_amount' => 10.0],
                        ['records' => [6], 'count' => 1, 'total_amount' => 5.0],
                    ],
                ],
                1, // 策略数量
                [3], // 每个策略的窗口数量
                [2], // 每个策略的可合并组数量
            ],
            'multiple_strategies' => [
                [
                    'month' => [
                        ['records' => [1, 2], 'count' => 2, 'total_amount' => 10.0],
                        ['records' => [3], 'count' => 1, 'total_amount' => 5.0],
                    ],
                    'week' => [
                        ['records' => [4, 5, 6], 'count' => 3, 'total_amount' => 15.0],
                    ],
                    'day' => [
                        ['records' => [7], 'count' => 1, 'total_amount' => 3.0],
                        ['records' => [8], 'count' => 1, 'total_amount' => 2.0],
                    ],
                ],
                3, // 策略数量
                [2, 1, 2], // 每个策略的窗口数量
                [1, 1, 0], // 每个策略的可合并组数量
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function calculateWindowMergePotentialDataProvider(): array
    {
        return [
            'mixed_mergeable_and_non_mergeable' => [
                [
                    ['records' => [1, 2, 3], 'count' => 3, 'total_amount' => 15.0],
                    ['records' => [4, 5], 'count' => 2, 'total_amount' => 10.0],
                    ['records' => [6], 'count' => 1, 'total_amount' => 5.0],
                    ['records' => [7, 8, 9, 10], 'count' => 4, 'total_amount' => 20.0],
                ],
                3, // 可合并窗口数
                9, // 可合并记录数 (3 + 2 + 4)
                45.0, // 可合并金额 (15 + 10 + 20)
            ],
            'all_mergeable' => [
                [
                    ['records' => [1, 2], 'count' => 2, 'total_amount' => 10.0],
                    ['records' => [3, 4], 'count' => 2, 'total_amount' => 8.0],
                ],
                2, // 可合并窗口数
                4, // 可合并记录数
                18.0, // 可合并金额
            ],
            'none_mergeable' => [
                [
                    ['records' => [1], 'count' => 1, 'total_amount' => 5.0],
                    ['records' => [2], 'count' => 1, 'total_amount' => 3.0],
                ],
                0, // 可合并窗口数
                0, // 可合并记录数
                0.0, // 可合并金额
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function calculateMergePotentialDataProvider(): array
    {
        return [
            'has_no_expiry_and_expiry_records' => [
                [
                    'no_expiry' => ['count' => 5, 'amount' => 25.0],
                    'with_expiry' => [
                        'by_window' => [
                            'day' => ['merge_potential' => ['mergeable_records' => 3, 'mergeable_amount' => 15.0]],
                            'week' => ['merge_potential' => ['mergeable_records' => 7, 'mergeable_amount' => 35.0]],
                            'month' => ['merge_potential' => ['mergeable_records' => 2, 'mergeable_amount' => 10.0]],
                        ],
                    ],
                ],
                true, // 无过期记录可合并
                5, // 无过期记录数
                25.0, // 无过期记录金额
                'week', // 最佳策略
            ],
            'no_expiry_single_record' => [
                [
                    'no_expiry' => ['count' => 1, 'amount' => 5.0],
                    'with_expiry' => [
                        'by_window' => [
                            'day' => ['merge_potential' => ['mergeable_records' => 4, 'mergeable_amount' => 20.0]],
                            'week' => ['merge_potential' => ['mergeable_records' => 2, 'mergeable_amount' => 10.0]],
                            'month' => ['merge_potential' => ['mergeable_records' => 1, 'mergeable_amount' => 5.0]],
                        ],
                    ],
                ],
                false, // 无过期记录不可合并（单条记录）
                0, // 无过期记录数
                0, // 无过期记录金额
                'day', // 最佳策略
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function determineOptimalStrategyDataProvider(): array
    {
        return [
            'month_strategy_best' => [
                [
                    'no_expiry' => ['count' => 2, 'amount' => 10.0],
                    'with_expiry' => [
                        'by_window' => [
                            'day' => ['merge_potential' => ['mergeable_records' => 3, 'mergeable_amount' => 15.0]],
                            'week' => ['merge_potential' => ['mergeable_records' => 5, 'mergeable_amount' => 25.0]],
                            'month' => ['merge_potential' => ['mergeable_records' => 8, 'mergeable_amount' => 40.0]],
                        ],
                    ],
                ],
                'month', // 最佳策略
                8, // 记录数
                40.0, // 金额
            ],
            'no_expiry_strategy_best' => [
                [
                    'no_expiry' => ['count' => 10, 'amount' => 50.0],
                    'with_expiry' => [
                        'by_window' => [
                            'day' => ['merge_potential' => ['mergeable_records' => 3, 'mergeable_amount' => 15.0]],
                            'week' => ['merge_potential' => ['mergeable_records' => 5, 'mergeable_amount' => 25.0]],
                            'month' => ['merge_potential' => ['mergeable_records' => 8, 'mergeable_amount' => 40.0]],
                        ],
                    ],
                ],
                'no_expiry', // 最佳策略
                10, // 记录数
                50.0, // 金额
            ],
        ];
    }
}
