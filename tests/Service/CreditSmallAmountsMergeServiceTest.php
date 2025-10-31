<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Model\ConsumptionPreview;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Service\CreditSmallAmountsMergeService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CreditSmallAmountsMergeService::class)]
#[RunTestsInSeparateProcesses]
final class CreditSmallAmountsMergeServiceTest extends AbstractIntegrationTestCase
{
    private CreditSmallAmountsMergeService $service;
    private TransactionRepository&MockObject $transactionRepository;
    private LoggerInterface&MockObject $logger;
    private Account $testAccount;

    protected function onSetUp(): void
    {
        // 创建 Mock 对象
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // 直接实例化服务并注入Mock依赖
        // 由于需要Mock所有依赖，使用直接实例化而非容器获取
        /* @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $this->service = new CreditSmallAmountsMergeService(
            $this->transactionRepository,
            $this->logger
        );

        // 创建测试用的账户
        $this->testAccount = new Account();
        // 注意：不能设置ID，因为它是由数据库自动生成的

        // 清理环境变量，确保测试隔离
        $this->clearEnvironmentVariables();
    }

    protected function onTearDown(): void
    {
        // 测试结束后清理环境变量
        $this->clearEnvironmentVariables();
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(CreditSmallAmountsMergeService::class, $this->service);
    }

    /**
     * 测试自动合并功能被禁用的场景.
     */
    public function testCheckAndMergeIfNeededAutoMergeDisabled(): void
    {
        // 设置自动合并禁用
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '0';

        // 不应该调用任何方法
        $this->transactionRepository->expects($this->never())->method('getConsumptionPreview');
        $this->logger->expects($this->never())->method('info');

        $this->service->checkAndMergeIfNeeded($this->testAccount, 150.0);
    }

    /**
     * 测试成本金额低于阈值的场景.
     */
    #[DataProvider('belowThresholdDataProvider')]
    public function testCheckAndMergeIfNeededBelowThreshold(float $costAmount, float $threshold): void
    {
        // 设置环境变量
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = (string) $threshold;

        // 不应该调用任何方法
        $this->transactionRepository->expects($this->never())->method('getConsumptionPreview');
        $this->logger->expects($this->never())->method('info');

        $this->service->checkAndMergeIfNeeded($this->testAccount, $costAmount);
    }

    /**
     * 测试不需要合并的场景.
     */
    #[DataProvider('noMergeNeededDataProvider')]
    public function testCheckAndMergeIfNeededNoMergeNeeded(
        float $costAmount,
        int $recordCount,
        int $threshold,
    ): void {
        // 设置环境变量
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = '100.0';
        $_ENV['CREDIT_AUTO_MERGE_THRESHOLD'] = (string) $threshold;

        // 创建模拟的预览对象
        $preview = $this->createMockConsumptionPreview($recordCount, false);

        $this->transactionRepository
            ->expects($this->once())
            ->method('getConsumptionPreview')
            ->with($this->testAccount, $costAmount, $threshold)
            ->willReturn($preview);

        // 不应该记录合并日志
        $this->logger->expects($this->never())->method('info');

        $this->service->checkAndMergeIfNeeded($this->testAccount, $costAmount);
    }

    /**
     * 测试需要合并的成功场景.
     */
    #[DataProvider('mergeNeededSuccessDataProvider')]
    public function testCheckAndMergeIfNeededMergeSuccess(
        float $costAmount,
        int $recordCount,
        int $threshold,
        string $timeWindowStrategy,
    ): void {
        // 设置环境变量
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = '100.0';
        $_ENV['CREDIT_AUTO_MERGE_THRESHOLD'] = (string) $threshold;
        $_ENV['CREDIT_TIME_WINDOW_STRATEGY'] = $timeWindowStrategy;
        $_ENV['CREDIT_MIN_AMOUNT_TO_MERGE'] = '5.0';

        // 创建模拟的预览对象
        $preview = $this->createMockConsumptionPreview($recordCount, true);

        $this->transactionRepository
            ->expects($this->once())
            ->method('getConsumptionPreview')
            ->with($this->testAccount, $costAmount, $threshold)
            ->willReturn($preview);

        // 验证日志记录
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with(self::callback(function (string $message): bool {
                return str_contains($message, '大额消费触发小额积分合并')
                       || str_contains($message, '小额积分合并完成');
            }));

        $this->service->checkAndMergeIfNeeded($this->testAccount, $costAmount);
    }

    /**
     * 测试使用默认环境变量值的场景.
     */
    public function testCheckAndMergeIfNeededDefaultEnvironmentValues(): void
    {
        // 不设置环境变量，使用默认值
        // CREDIT_AUTO_MERGE_ENABLED 默认为 true
        // CREDIT_AUTO_MERGE_THRESHOLD 默认为 100
        // CREDIT_AUTO_MERGE_MIN_AMOUNT 默认为 100.0
        // CREDIT_TIME_WINDOW_STRATEGY 默认为 'monthly'

        $costAmount = 150.0;
        $expectedRecordCount = 120;

        // 创建模拟的预览对象
        $preview = $this->createMockConsumptionPreview($expectedRecordCount, true);

        $this->transactionRepository
            ->expects($this->once())
            ->method('getConsumptionPreview')
            ->with($this->testAccount, $costAmount, 100) // 默认阈值
            ->willReturn($preview);

        // 验证日志记录，确认使用了默认的策略值
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with(
                self::callback(function (string $message): bool {
                    return str_contains($message, '大额消费触发小额积分合并')
                           || str_contains($message, '小额积分合并完成');
                }),
                self::callback(function ($context): bool {
                    // 验证上下文是数组类型且包含基本键
                    return is_array($context)
                           && (isset($context['account']) || isset($context['strategy']));
                })
            );

        $this->service->checkAndMergeIfNeeded($this->testAccount, $costAmount);
    }

    /**
     * 测试复杂环境变量配置场景.
     *
     * @param array<string, string> $envConfig
     */
    #[DataProvider('complexEnvironmentConfigDataProvider')]
    public function testCheckAndMergeIfNeededComplexEnvironmentConfig(
        array $envConfig,
        float $costAmount,
        bool $shouldProcess,
        ?int $expectedThreshold = null,
    ): void {
        // 设置环境变量
        foreach ($envConfig as $key => $value) {
            $_ENV[$key] = $value;
        }

        if ($shouldProcess) {
            $recordCount = 150;
            $preview = $this->createMockConsumptionPreview($recordCount, true);

            $this->transactionRepository
                ->expects($this->once())
                ->method('getConsumptionPreview')
                ->with($this->testAccount, $costAmount, $expectedThreshold ?? 100)
                ->willReturn($preview);

            $this->logger
                ->expects($this->exactly(2))
                ->method('info');
        } else {
            $this->transactionRepository->expects($this->never())->method('getConsumptionPreview');
            $this->logger->expects($this->never())->method('info');
        }

        $this->service->checkAndMergeIfNeeded($this->testAccount, $costAmount);
    }

    /**
     * 测试日志记录的详细信息.
     */
    public function testLoggingDetails(): void
    {
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = '100.0';
        $_ENV['CREDIT_AUTO_MERGE_THRESHOLD'] = '80';
        $_ENV['CREDIT_TIME_WINDOW_STRATEGY'] = 'weekly';

        $costAmount = 200.0;
        $recordCount = 95;
        $preview = $this->createMockConsumptionPreview($recordCount, true);

        $this->transactionRepository
            ->expects($this->once())
            ->method('getConsumptionPreview')
            ->willReturn($preview);

        // 验证日志调用
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with(
                self::callback(function (string $message): bool {
                    return str_contains($message, '大额消费触发小额积分合并')
                           || str_contains($message, '小额积分合并完成');
                }),
                self::callback(function ($context): bool {
                    // 验证上下文包含基本必需的键
                    return is_array($context) && isset($context['account']) && isset($context['strategy']);
                })
            );

        $this->service->checkAndMergeIfNeeded($this->testAccount, $costAmount);
    }

    /**
     * 测试边界条件：成本金额正好等于阈值
     */
    public function testCheckAndMergeIfNeededExactThreshold(): void
    {
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = '100.0';

        $costAmount = 100.0; // 正好等于阈值

        $preview = $this->createMockConsumptionPreview(120, true);

        $this->transactionRepository
            ->expects($this->once())
            ->method('getConsumptionPreview')
            ->willReturn($preview);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $this->service->checkAndMergeIfNeeded($this->testAccount, $costAmount);
    }

    /**
     * 测试TODO实现的方法返回值
     */
    public function testExecuteMergeSmallAmountsTodoImplementation(): void
    {
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = '100.0';

        $preview = $this->createMockConsumptionPreview(150, true);

        $this->transactionRepository
            ->expects($this->once())
            ->method('getConsumptionPreview')
            ->willReturn($preview);

        // 验证日志调用（包含完成日志）
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                self::callback(function (string $message): bool {
                    return str_contains($message, '小额积分合并完成')
                           || str_contains($message, '大额消费触发小额积分合并');
                }),
                self::callback(function ($context): bool {
                    // 如果是完成日志，验证mergeCount为0
                    return !is_array($context) || !isset($context['mergeCount']) || 0 === $context['mergeCount'];
                })
            );

        $this->service->checkAndMergeIfNeeded($this->testAccount, 150.0);
    }

    // ============= 辅助方法 =============

    /**
     * 清理环境变量.
     */
    private function clearEnvironmentVariables(): void
    {
        $envVars = [
            'CREDIT_AUTO_MERGE_ENABLED',
            'CREDIT_AUTO_MERGE_THRESHOLD',
            'CREDIT_AUTO_MERGE_MIN_AMOUNT',
            'CREDIT_TIME_WINDOW_STRATEGY',
            'CREDIT_MIN_AMOUNT_TO_MERGE',
        ];

        foreach ($envVars as $var) {
            if (isset($_ENV[$var])) {
                unset($_ENV[$var]);
            }
        }
    }

    /**
     * 创建模拟的消费预览对象
     */
    private function createMockConsumptionPreview(int $recordCount, bool $needsMerge): ConsumptionPreview
    {
        return new ConsumptionPreview([], $needsMerge, $recordCount);
    }

    // ============= DataProvider 方法 =============

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function belowThresholdDataProvider(): array
    {
        return [
            'cost_50_threshold_100' => [50.0, 100.0],
            'cost_99.99_threshold_100' => [99.99, 100.0],
            'cost_25_threshold_50' => [25.0, 50.0],
            'cost_0_threshold_100' => [0.0, 100.0],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function noMergeNeededDataProvider(): array
    {
        return [
            'low_record_count' => [150.0, 50, 100], // 记录数少于阈值
            'exact_threshold' => [200.0, 100, 100], // 记录数正好等于阈值
            'high_threshold' => [180.0, 80, 150], // 阈值很高
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function mergeNeededSuccessDataProvider(): array
    {
        return [
            'monthly_strategy' => [150.0, 120, 100, 'monthly'],
            'weekly_strategy' => [200.0, 150, 80, 'weekly'],
            'daily_strategy' => [300.0, 200, 100, 'daily'],
            'high_cost_many_records' => [500.0, 300, 150, 'monthly'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function complexEnvironmentConfigDataProvider(): array
    {
        return [
            'disabled_auto_merge' => [
                ['CREDIT_AUTO_MERGE_ENABLED' => '0'],
                200.0,
                false, // 不应该处理
            ],
            'empty_string_enabled' => [
                ['CREDIT_AUTO_MERGE_ENABLED' => ''],
                200.0,
                false, // 空字符串应该被视为禁用
            ],
            'custom_threshold_and_min_amount' => [
                [
                    'CREDIT_AUTO_MERGE_ENABLED' => '1',
                    'CREDIT_AUTO_MERGE_THRESHOLD' => '75',
                    'CREDIT_AUTO_MERGE_MIN_AMOUNT' => '150.0',
                    'CREDIT_TIME_WINDOW_STRATEGY' => 'weekly',
                ],
                200.0,
                true,
                75, // 期望的阈值
            ],
            'missing_threshold_use_default' => [
                [
                    'CREDIT_AUTO_MERGE_ENABLED' => '1',
                    'CREDIT_AUTO_MERGE_MIN_AMOUNT' => '100.0',
                ],
                150.0,
                true,
                100, // 默认阈值
            ],
        ];
    }
}
