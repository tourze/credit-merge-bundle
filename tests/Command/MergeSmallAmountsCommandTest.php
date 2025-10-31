<?php

namespace CreditMergeBundle\Tests\Command;

use CreditMergeBundle\Command\MergeSmallAmountsCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(MergeSmallAmountsCommand::class)]
#[RunTestsInSeparateProcesses]
final class MergeSmallAmountsCommandTest extends AbstractCommandTestCase
{
    protected function getCommandTester(): CommandTester
    {
        $command = self::getContainer()->get(MergeSmallAmountsCommand::class);
        self::assertInstanceOf(MergeSmallAmountsCommand::class, $command);
        $application = new Application();
        $application->add($command);
        $command = $application->find('credit:merge-small-amounts');

        return new CommandTester($command);
    }

    protected function onSetUp(): void
    {
        // Setup for merge small amounts command tests
    }

    /**
     * 测试命令执行 - 无账户 ID 参数的情况（处理所有账户）.
     */
    public function testExecuteAllAccounts(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令（集成测试，会加载fixtures中的测试账户）
        $exitCode = $commandTester->execute([
            '--min-amount' => '5.0',
            '--strategy' => 'month',
            '--batch-size' => '100',
        ]);

        // 验证输出（有账户时的输出）
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('未指定账户，将处理所有有效账户', $output);
        $this->assertStringContainsString('小额积分合并工具', $output);
        $this->assertEquals(0, $exitCode); // Command::SUCCESS 当有账户时
    }

    /**
     * 测试命令执行 - 指定账户 ID 的情况.
     */
    public function testExecuteSpecificAccount(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令（指定不存在的账户ID）
        $exitCode = $commandTester->execute([
            'account-id' => '999',
            '--min-amount' => '5.0',
            '--strategy' => 'month',
            '--batch-size' => '100',
        ]);

        // 验证输出和退出代码
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('账户不存在: 999', $output);
        $this->assertEquals(1, $exitCode); // Command::FAILURE
    }

    /**
     * 测试命令执行 - 使用不同的合并策略.
     */
    public function testExecuteWithDifferentStrategy(): void
    {
        $commandTester = $this->getCommandTester();

        // 测试day策略
        $exitCode = $commandTester->execute([
            'account-id' => '999',
            '--min-amount' => '5.0',
            '--strategy' => 'day',
            '--batch-size' => '100',
        ]);

        // 验证输出和退出代码
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('账户不存在: 999', $output);
        $this->assertEquals(1, $exitCode); // Command::FAILURE
    }

    /**
     * 测试命令执行 - 使用模拟模式.
     */
    public function testExecuteDryRunMode(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令（模拟模式）
        $exitCode = $commandTester->execute([
            'account-id' => '999',
            '--min-amount' => '5.0',
            '--strategy' => 'month',
            '--dry-run' => true,
        ]);

        // 验证输出和退出代码
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('账户不存在: 999', $output);
        $this->assertEquals(1, $exitCode); // Command::FAILURE
    }

    /**
     * 测试命令执行 - 无效的策略.
     */
    public function testExecuteInvalidStrategy(): void
    {
        $commandTester = $this->getCommandTester();

        // 执行命令（无效策略）
        $exitCode = $commandTester->execute([
            '--strategy' => 'invalid',
        ]);

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('无效的时间窗口策略: invalid', $output);
        $this->assertStringContainsString('可用的策略:', $output);
        $this->assertEquals(1, $exitCode); // Command::FAILURE
    }

    /**
     * 测试 account-id 参数.
     */
    public function testArgumentAccountId(): void
    {
        $commandTester = $this->getCommandTester();

        // 测试指定有效账户ID
        $exitCode = $commandTester->execute([
            'account-id' => '123',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('账户不存在: 123', $output);
        $this->assertEquals(1, $exitCode);
    }

    /**
     * 测试 min-amount 选项.
     */
    public function testOptionMinAmount(): void
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute([
            '--min-amount' => '10.0',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('未指定账户，将处理所有有效账户', $output);
        $this->assertEquals(0, $exitCode);
    }

    /**
     * 测试 batch-size 选项.
     */
    public function testOptionBatchSize(): void
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute([
            '--batch-size' => '50',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('未指定账户，将处理所有有效账户', $output);
        $this->assertEquals(0, $exitCode);
    }

    /**
     * 测试 strategy 选项.
     */
    public function testOptionStrategy(): void
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute([
            '--strategy' => 'day',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('未指定账户，将处理所有有效账户', $output);
        $this->assertEquals(0, $exitCode);
    }

    /**
     * 测试 dry-run 选项.
     */
    public function testOptionDryRun(): void
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute([
            '--dry-run' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('未指定账户，将处理所有有效账户', $output);
        $this->assertEquals(0, $exitCode);
    }
}
