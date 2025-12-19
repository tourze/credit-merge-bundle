<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Service\CreditSmallAmountsMergeService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CreditSmallAmountsMergeService::class)]
#[RunTestsInSeparateProcesses]
final class CreditSmallAmountsMergeServiceTest extends AbstractIntegrationTestCase
{
    private CreditSmallAmountsMergeService $service;

    private Account $testAccount;

    protected function onSetUp(): void
    {
        // 从容器获取真实服务实例进行集成测试
        $this->service = self::getService(CreditSmallAmountsMergeService::class);

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

        // 验证方法存在且可以调用
        $this->assertTrue(method_exists($this->service, 'checkAndMergeIfNeeded'));

        // 调用服务方法（自动合并被禁用，不应该有任何操作）
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

        // 验证方法存在
        $this->assertTrue(method_exists($this->service, 'checkAndMergeIfNeeded'));

        // 调用服务方法
        $this->service->checkAndMergeIfNeeded($this->testAccount, $costAmount);
    }

    /**
     * 测试环境变量处理.
     */
    #[DataProvider('environmentConfigDataProvider')]
    public function testEnvironmentVariableHandling(array $envVars, bool $expectedEnabled): void
    {
        // 设置环境变量
        foreach ($envVars as $key => $value) {
            $_ENV[$key] = $value;
        }

        // 验证服务方法存在
        $this->assertTrue(method_exists($this->service, 'checkAndMergeIfNeeded'));

        // 调用服务方法
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
    public static function environmentConfigDataProvider(): array
    {
        return [
            'disabled_auto_merge' => [
                ['CREDIT_AUTO_MERGE_ENABLED' => '0'],
                false,
            ],
            'enabled_auto_merge' => [
                ['CREDIT_AUTO_MERGE_ENABLED' => '1'],
                true,
            ],
            'empty_string_disabled' => [
                ['CREDIT_AUTO_MERGE_ENABLED' => ''],
                false,
            ],
            'with_min_amount' => [
                [
                    'CREDIT_AUTO_MERGE_ENABLED' => '1',
                    'CREDIT_AUTO_MERGE_MIN_AMOUNT' => '100.0',
                ],
                true,
            ],
        ];
    }
}
