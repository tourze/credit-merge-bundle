<?php

namespace CreditMergeBundle\Tests\Command;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\Currency;
use CreditBundle\Repository\AccountRepository;
use CreditMergeBundle\Command\MergeSmallAmountsCommand;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Service\CreditMergeService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class MergeSmallAmountsCommandTest extends TestCase
{
    private AccountRepository $accountRepository;
    private CreditMergeService $mergeService;
    private LoggerInterface $logger;
    private MergeSmallAmountsCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->accountRepository = $this->createMock(AccountRepository::class);
        $this->mergeService = $this->createMock(CreditMergeService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->command = new MergeSmallAmountsCommand(
            $this->accountRepository,
            $this->mergeService,
            $this->logger
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * 测试命令执行 - 无账户 ID 参数的情况（处理所有账户）
     */
    public function testExecute_allAccounts(): void
    {
        // 创建模拟账户数据
        $currency = $this->createMock(Currency::class);
        $currency->method('__toString')->willReturn('CNY');

        $account1 = $this->createMock(Account::class);
        $account1->method('getId')->willReturn(101);
        $account1->method('getName')->willReturn('Account 1');
        $account1->method('getCurrency')->willReturn($currency);

        $account2 = $this->createMock(Account::class);
        $account2->method('getId')->willReturn(102);
        $account2->method('getName')->willReturn('Account 2');
        $account2->method('getCurrency')->willReturn($currency);

        $accounts = [$account1, $account2];

        // 设置预期行为：返回所有账户
        $this->accountRepository->expects($this->once())
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn($accounts);

        // 为每个账户创建模拟统计数据
        $stats1 = new SmallAmountStats($account1, 5, 10.0, 5.0);
        $stats2 = new SmallAmountStats($account2, 3, 6.0, 5.0);

        // 设置合并服务的预期行为
        $this->mergeService->expects($this->exactly(2))
            ->method('getDetailedSmallAmountStats')
            ->willReturnMap([
                [$account1, 5.0, TimeWindowStrategy::MONTH, $stats1],
                [$account2, 5.0, TimeWindowStrategy::MONTH, $stats2]
            ]);

        $this->mergeService->expects($this->exactly(2))
            ->method('mergeSmallAmounts')
            ->willReturnMap([
                [$account1, 5.0, 100, TimeWindowStrategy::MONTH, 4],
                [$account2, 5.0, 100, TimeWindowStrategy::MONTH, 2]
            ]);

        // 执行命令
        $this->commandTester->execute([
            '--min-amount' => '5.0',
            '--strategy' => 'month',
            '--batch-size' => '100',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('未指定账户，将处理所有有效账户', $output);
        $this->assertStringContainsString('账户: 101', $output);
        $this->assertStringContainsString('账户: 102', $output);
        $this->assertStringContainsString('合并了: 4 条', $output);
        $this->assertStringContainsString('合并了: 2 条', $output);
    }

    /**
     * 测试命令执行 - 指定账户 ID 的情况
     */
    public function testExecute_specificAccount(): void
    {
        // 创建模拟账户数据
        $currency = $this->createMock(Currency::class);
        $currency->method('__toString')->willReturn('CNY');

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(101);
        $account->method('getName')->willReturn('Account 1');
        $account->method('getCurrency')->willReturn($currency);

        // 设置预期行为：返回指定账户
        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with(101)
            ->willReturn($account);

        // 创建模拟统计数据
        $stats = new SmallAmountStats($account, 5, 10.0, 5.0);

        // 设置合并服务的预期行为
        $this->mergeService->expects($this->once())
            ->method('getDetailedSmallAmountStats')
            ->with($account, 5.0, TimeWindowStrategy::MONTH)
            ->willReturn($stats);

        $this->mergeService->expects($this->once())
            ->method('mergeSmallAmounts')
            ->with($account, 5.0, 100, TimeWindowStrategy::MONTH)
            ->willReturn(4);

        // 执行命令
        $this->commandTester->execute([
            'account-id' => '101',
            '--min-amount' => '5.0',
            '--strategy' => 'month',
            '--batch-size' => '100',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('处理账户: 101', $output);
        $this->assertStringContainsString('合并了: 4 条', $output);
    }

    /**
     * 测试命令执行 - 账户不存在的情况
     */
    public function testExecute_accountNotFound(): void
    {
        // 设置预期行为：返回null表示账户不存在
        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        // 执行命令
        $this->commandTester->execute([
            'account-id' => '999',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('账户不存在: 999', $output);
    }

    /**
     * 测试命令执行 - 使用不同的合并策略
     */
    public function testExecute_withDifferentStrategy(): void
    {
        // 创建模拟账户数据
        $currency = $this->createMock(Currency::class);
        $currency->method('__toString')->willReturn('CNY');

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(101);
        $account->method('getName')->willReturn('Account 1');
        $account->method('getCurrency')->willReturn($currency);

        // 设置预期行为：返回指定账户
        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with(101)
            ->willReturn($account);

        // 创建模拟统计数据
        $stats = new SmallAmountStats($account, 5, 10.0, 5.0);

        // 设置合并服务的预期行为
        $this->mergeService->expects($this->once())
            ->method('getDetailedSmallAmountStats')
            ->with($account, 5.0, TimeWindowStrategy::DAY)
            ->willReturn($stats);

        $this->mergeService->expects($this->once())
            ->method('mergeSmallAmounts')
            ->with($account, 5.0, 100, TimeWindowStrategy::DAY)
            ->willReturn(4);

        // 执行命令
        $this->commandTester->execute([
            'account-id' => '101',
            '--min-amount' => '5.0',
            '--strategy' => 'day',
            '--batch-size' => '100',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('处理账户: 101', $output);
        $this->assertStringContainsString('合并了: 4 条', $output);
    }

    /**
     * 测试命令执行 - 使用模拟模式
     */
    public function testExecute_dryRunMode(): void
    {
        // 创建模拟账户数据
        $currency = $this->createMock(Currency::class);
        $currency->method('__toString')->willReturn('CNY');

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(101);
        $account->method('getName')->willReturn('Account 1');
        $account->method('getCurrency')->willReturn($currency);

        // 设置预期行为：返回指定账户
        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with(101)
            ->willReturn($account);

        // 创建模拟统计数据
        $stats = new SmallAmountStats($account, 5, 10.0, 5.0, TimeWindowStrategy::MONTH);
        $stats->addGroupStats('2023-10', 3, 6.0, new \DateTime('2023-10-31'));
        $stats->addGroupStats('2023-11', 2, 4.0, new \DateTime('2023-11-30'));

        // 设置合并服务的预期行为
        $this->mergeService->expects($this->once())
            ->method('getDetailedSmallAmountStats')
            ->with($account, 5.0, TimeWindowStrategy::MONTH)
            ->willReturn($stats);

        // 不应调用合并服务
        $this->mergeService->expects($this->never())
            ->method('mergeSmallAmounts');

        // 执行命令
        $this->commandTester->execute([
            'account-id' => '101',
            '--min-amount' => '5.0',
            '--strategy' => 'month',
            '--dry-run' => true,
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('处理账户: 101', $output);
        $this->assertStringContainsString('当前为模拟模式，不会实际合并记录', $output);
        $this->assertStringContainsString('模拟模式，跳过实际合并', $output);
    }

    /**
     * 测试命令执行 - 没有可合并的记录
     */
    public function testExecute_noMergeableRecords(): void
    {
        // 创建模拟账户数据
        $currency = $this->createMock(Currency::class);
        $currency->method('__toString')->willReturn('CNY');

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(101);
        $account->method('getName')->willReturn('Account 1');
        $account->method('getCurrency')->willReturn($currency);

        // 设置预期行为：返回指定账户
        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with(101)
            ->willReturn($account);

        // 创建模拟统计数据 - 只有1条记录，不可合并
        $stats = new SmallAmountStats($account, 1, 3.0, 5.0);

        // 设置合并服务的预期行为
        $this->mergeService->expects($this->once())
            ->method('getDetailedSmallAmountStats')
            ->with($account, 5.0, TimeWindowStrategy::MONTH)
            ->willReturn($stats);

        // 由于没有可合并的记录，不应调用合并方法
        $this->mergeService->expects($this->never())
            ->method('mergeSmallAmounts');

        // 执行命令
        $this->commandTester->execute([
            'account-id' => '101',
            '--min-amount' => '5.0',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('处理账户: 101', $output);
        $this->assertStringContainsString('没有需要合并的记录，跳过', $output);
    }
}
