<?php

namespace CreditMergeBundle\Command;

use CreditBundle\Repository\AccountRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Service\CreditMergeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

/**
 * 合并小额积分记录命令
 * 用于定期合并账户中的小额积分记录，减少数据库记录数量
 */
#[AsCommand(
    name: 'credit:merge-small-amounts',
    description: '合并账户中的小额积分记录，减少记录数量',
)]
#[AsCronTask(expression: '0 2 * * *')] // 每天凌晨2点执行
class MergeSmallAmountsCommand extends Command
{
    /**
     * 命令名称常量
     */
    public const NAME = 'credit:merge-small-amounts';

    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly CreditMergeService $mergeService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('account-id', InputArgument::OPTIONAL, '账户ID，不提供则处理所有账户')
            ->addOption('min-amount', 'm', InputOption::VALUE_REQUIRED, '最小合并金额，低于此金额的记录将被合并', 5.0)
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, '每批处理的记录数', 100)
            ->addOption('strategy', 's', InputOption::VALUE_REQUIRED,
                '时间窗口策略 (' . implode(', ', array_keys(TimeWindowStrategy::getOptions())) . ')',
                TimeWindowStrategy::MONTH->value
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '仅模拟执行，不实际合并');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $accountId = $input->getArgument('account-id');
        $minAmount = floatval($input->getOption('min-amount'));
        $batchSize = intval($input->getOption('batch-size'));
        $dryRun = $input->getOption('dry-run');

        // 解析时间窗口策略
        $strategyName = $input->getOption('strategy');
        $strategy = TimeWindowStrategy::fromString($strategyName);

        if ($strategy === null) {
            $io->error('无效的时间窗口策略: ' . $strategyName);
            $io->text('可用的策略: ' . implode(', ', array_keys(TimeWindowStrategy::getOptions())));
            return Command::FAILURE;
        }

        $io->title('小额积分合并工具');

        // 显示配置信息
        $io->definitionList(
            ['最小合并金额' => $minAmount],
            ['每批处理记录数' => $batchSize],
            ['时间窗口策略' => $strategy->value . ' - ' . $strategy->getLabel()],
            ['模拟模式' => $dryRun ? '是' : '否']
        );

        if ($dryRun) {
            $io->warning('当前为模拟模式，不会实际合并记录');
        }

        $this->logger->info('开始执行小额积分合并', [
            'min_amount' => $minAmount,
            'batch_size' => $batchSize,
            'strategy' => $strategy->value,
            'dry_run' => $dryRun,
            'account_id' => $accountId,
        ]);

        // 处理指定账户或所有账户
        if ($accountId) {
            $account = $this->accountRepository->find($accountId);
            if (!$account) {
                $io->error('账户不存在: ' . $accountId);
                $this->logger->error('账户不存在', ['account_id' => $accountId]);
                return Command::FAILURE;
            }

            $accounts = [$account];
        } else {
            $io->info('未指定账户，将处理所有有效账户');
            $accounts = $this->accountRepository->findBy(['enabled' => true]);
            $this->logger->info('准备处理所有有效账户', ['count' => count($accounts)]);
        }

        $totalProcessed = 0;
        $totalMerged = 0;
        $startTime = microtime(true);

        foreach ($accounts as $account) {
            $io->section('处理账户: ' . $account->getId() . ' (' . $account->getName() . ')');

            // 获取详细的小额积分统计信息
            $stats = $this->mergeService->getDetailedSmallAmountStats($account, $minAmount, $strategy);

            $io->info(sprintf(
                '发现 %d 条小额积分记录，总金额: %.2f %s，平均金额: %.2f',
                $stats->getCount(),
                $stats->getTotal(),
                $account->getCurrency(),
                $stats->getAverageAmount()
            ));

            $this->logger->debug('账户小额积分统计', [
                'account_id' => $account->getId(),
                'count' => $stats->getCount(),
                'total' => $stats->getTotal(),
                'average' => $stats->getAverageAmount(),
            ]);

            if (!$stats->hasMergeableRecords()) {
                $io->text('没有需要合并的记录，跳过');
                continue;
            }

            // 显示分组统计
            $groupStats = $stats->getGroupStats();

            if (!empty($groupStats)) {
                $io->text('按 ' . $strategy->value . ' 策略分组结果:');

                $groupTable = [];
                foreach ($groupStats as $key => $group) {
                    $groupTable[] = [
                        '分组' => $key,
                        '记录数' => $group['count'],
                        '金额' => number_format($group['total'], 2),
                        '最早过期时间' => $group['earliest_expiry'] ?? '无'
                    ];
                }

                $io->table(array_keys($groupTable[0]), $groupTable);

                $io->text(sprintf(
                    '预计合并后可减少 %d 条记录 (%.1f%%)',
                    $stats->getPotentialRecordReduction(),
                    $stats->getMergeEfficiency()
                ));
            }

            if ($dryRun) {
                $io->text('模拟模式，跳过实际合并');
                $totalProcessed += $stats->getCount();
                continue;
            }

            // 真实执行合并
            $io->text('开始合并记录...');
            $accountStartTime = microtime(true);

            try {
                $mergeCount = $this->mergeService->mergeSmallAmounts(
                    $account,
                    $minAmount,
                    $batchSize,
                    $strategy
                );

                $totalMerged += $mergeCount;
                $totalProcessed += $stats->getCount();
                $accountExecutionTime = microtime(true) - $accountStartTime;

                $io->success(sprintf(
                    '合并完成，处理了 %d 条记录，合并了: %d 条',
                    $stats->getCount(),
                    $mergeCount
                ));

                $io->text(sprintf('执行时间: %.2f 秒', $accountExecutionTime));

                $this->logger->info('账户积分合并完成', [
                    'account_id' => $account->getId(),
                    'processed' => $stats->getCount(),
                    'merged' => $mergeCount,
                    'execution_time' => $accountExecutionTime,
                ]);

            } catch (\Exception $e) {
                $io->error('合并过程中发生错误: ' . $e->getMessage());
                $this->logger->error('积分合并出错', [
                    'account_id' => $account->getId(),
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                if ($output->isVerbose()) {
                    $io->text($e->getTraceAsString());
                }
                // 不中断其他账户的处理
            }
        }

        $totalExecutionTime = microtime(true) - $startTime;

        // 总结
        $io->section('执行总结');
        $io->definitionList(
            ['处理的账户数量' => count($accounts)],
            ['处理的记录总数' => $totalProcessed],
            ['合并的记录总数' => $dryRun ? '(模拟模式)' : $totalMerged],
            ['总执行时间' => sprintf('%.2f 秒', $totalExecutionTime)],
        );

        $this->logger->info('积分合并任务完成', [
            'accounts_count' => count($accounts),
            'records_processed' => $totalProcessed,
            'records_merged' => $dryRun ? 0 : $totalMerged,
            'execution_time' => $totalExecutionTime,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }
}
