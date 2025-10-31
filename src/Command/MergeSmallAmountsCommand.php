<?php

namespace CreditMergeBundle\Command;

use CreditBundle\Entity\Account;
use CreditBundle\Repository\AccountRepository;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use CreditMergeBundle\Service\CreditMergeService;
use Monolog\Attribute\WithMonologChannel;
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
 * 用于定期合并账户中的小额积分记录，减少数据库记录数量.
 */
#[AsCommand(
    name: self::NAME,
    description: '合并账户中的小额积分记录，减少记录数量',
)]
#[AsCronTask(expression: '0 2 * * *')] // 每天凌晨2点执行
#[WithMonologChannel(channel: 'credit_merge')]
class MergeSmallAmountsCommand extends Command
{
    /**
     * 命令名称常量.
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
                '时间窗口策略 ('.implode(', ', array_keys(TimeWindowStrategy::getOptions())).')',
                TimeWindowStrategy::MONTH->value
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '仅模拟执行，不实际合并')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $config = $this->parseCommandInput($input);
        if (null === $config['strategy']) {
            return $this->handleInvalidStrategy($io, $input->getOption('strategy'));
        }

        $this->displayConfiguration($io, $config);
        $this->logCommandStart($config);

        $accountId = $config['account_id'];
        $accounts = $this->resolveAccounts($io, is_string($accountId) ? $accountId : null);
        if ([] === $accounts) {
            return Command::FAILURE;
        }

        return $this->processAccounts($io, $accounts, $config);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCommandInput(InputInterface $input): array
    {
        $strategyName = $input->getOption('strategy');
        $strategy = null;
        if (is_string($strategyName)) {
            $strategy = TimeWindowStrategy::fromString($strategyName);
        }

        $accountId = $input->getArgument('account-id');
        $minAmount = $input->getOption('min-amount');
        $batchSize = $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');

        return [
            'account_id' => is_string($accountId) ? $accountId : null,
            'min_amount' => is_numeric($minAmount) ? floatval($minAmount) : 5.0,
            'batch_size' => is_numeric($batchSize) ? intval($batchSize) : 100,
            'dry_run' => (bool) $dryRun,
            'strategy' => $strategy,
        ];
    }

    private function handleInvalidStrategy(SymfonyStyle $io, mixed $strategyName): int
    {
        $strategyDisplay = is_string($strategyName) ? $strategyName : gettype($strategyName);
        $io->error('无效的时间窗口策略: '.$strategyDisplay);
        $io->text('可用的策略: '.implode(', ', array_keys(TimeWindowStrategy::getOptions())));

        return Command::FAILURE;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function displayConfiguration(SymfonyStyle $io, array $config): void
    {
        $io->title('小额积分合并工具');

        $io->definitionList(
            ['最小合并金额' => $config['min_amount']],
            ['每批处理记录数' => $config['batch_size']],
            ['时间窗口策略' => $config['strategy'] instanceof TimeWindowStrategy ? $config['strategy']->value.' - '.$config['strategy']->getLabel() : 'N/A'],
            ['模拟模式' => (bool) $config['dry_run'] ? '是' : '否']
        );

        if ((bool) $config['dry_run']) {
            $io->warning('当前为模拟模式，不会实际合并记录');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function logCommandStart(array $config): void
    {
        $this->logger->info('开始执行小额积分合并', [
            'min_amount' => $config['min_amount'],
            'batch_size' => $config['batch_size'],
            'strategy' => $config['strategy'] instanceof TimeWindowStrategy ? $config['strategy']->value : null,
            'dry_run' => $config['dry_run'],
            'account_id' => $config['account_id'],
        ]);
    }

    /**
     * @return array<Account>
     */
    private function resolveAccounts(SymfonyStyle $io, ?string $accountId): array
    {
        if (null !== $accountId) {
            $account = $this->accountRepository->find($accountId);
            if (null === $account) {
                $io->error('账户不存在: '.$accountId);
                $this->logger->error('账户不存在', ['account_id' => $accountId]);

                return [];
            }
            \assert($account instanceof Account);

            return [$account];
        }

        $io->info('未指定账户，将处理所有有效账户');
        /** @var array<Account> $accounts */
        $accounts = $this->accountRepository->findAll();
        $this->logger->info('准备处理所有有效账户', ['count' => count($accounts)]);

        return $accounts;
    }

    /**
     * @param array<Account>       $accounts
     * @param array<string, mixed> $config
     */
    private function processAccounts(SymfonyStyle $io, array $accounts, array $config): int
    {
        $totalProcessed = 0;
        $totalMerged = 0;
        $startTime = microtime(true);

        foreach ($accounts as $account) {
            $result = $this->processAccount($io, $account, $config);
            $totalProcessed += $result['processed'];
            $totalMerged += $result['merged'];
        }

        $dryRun = $config['dry_run'];
        $this->displaySummary($io, $accounts, $totalProcessed, $totalMerged, $startTime, is_bool($dryRun) ? $dryRun : false);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, int>
     */
    private function processAccount(SymfonyStyle $io, Account $account, array $config): array
    {
        $io->section('处理账户: '.$account->getId().' ('.$account->getName().')');

        $strategy = $config['strategy'];
        if (!$strategy instanceof TimeWindowStrategy) {
            throw new \InvalidArgumentException('Invalid strategy');
        }

        $minAmount = $config['min_amount'];
        $stats = $this->mergeService->getDetailedSmallAmountStats($account, is_numeric($minAmount) ? (float) $minAmount : 5.0, $strategy);
        $this->displayAccountStats($io, $account, $stats, $strategy);

        if (!$stats->hasMergeableRecords()) {
            $io->text('没有需要合并的记录，跳过');

            return ['processed' => 0, 'merged' => 0];
        }

        $this->displayGroupStats($io, $stats, $strategy);

        if ((bool) $config['dry_run']) {
            $io->text('模拟模式，跳过实际合并');

            return ['processed' => $stats->getCount(), 'merged' => 0];
        }

        return $this->executeAccountMerge($io, $account, $stats, $config, $strategy);
    }

    private function displayAccountStats(SymfonyStyle $io, Account $account, SmallAmountStats $stats, TimeWindowStrategy $strategy): void
    {
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
    }

    private function displayGroupStats(SymfonyStyle $io, SmallAmountStats $stats, TimeWindowStrategy $strategy): void
    {
        $groupStats = $stats->getGroupStats();
        if ([] === $groupStats) {
            return;
        }

        $io->text('按 '.$strategy->value.' 策略分组结果:');

        $groupTable = [];
        foreach ($groupStats as $key => $group) {
            $groupTable[] = [
                '分组' => $key,
                '记录数' => $group['count'],
                '金额' => number_format(is_numeric($group['total']) ? (float) $group['total'] : 0.0, 2),
                '最早过期时间' => $group['earliest_expiry'] ?? '无',
            ];
        }

        $io->table(array_keys($groupTable[0]), $groupTable);

        $io->text(sprintf(
            '预计合并后可减少 %d 条记录 (%.1f%%)',
            $stats->getPotentialRecordReduction(),
            $stats->getMergeEfficiency()
        ));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, int>
     */
    private function executeAccountMerge(SymfonyStyle $io, Account $account, SmallAmountStats $stats, array $config, TimeWindowStrategy $strategy): array
    {
        $io->text('开始合并记录...');
        $accountStartTime = microtime(true);

        try {
            $minAmount = $config['min_amount'];
            $batchSize = $config['batch_size'];

            $mergeCount = $this->mergeService->mergeSmallAmounts(
                $account,
                is_numeric($minAmount) ? (float) $minAmount : 5.0,
                is_numeric($batchSize) ? (int) $batchSize : 100,
                $strategy
            );

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

            return ['processed' => $stats->getCount(), 'merged' => $mergeCount];
        } catch (\Throwable $e) {
            $this->handleMergeError($io, $account, $e);

            return ['processed' => $stats->getCount(), 'merged' => 0];
        }
    }

    private function handleMergeError(SymfonyStyle $io, Account $account, \Throwable $e): void
    {
        $io->error('合并过程中发生错误: '.$e->getMessage());
        $this->logger->error('积分合并出错', [
            'account_id' => $account->getId(),
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * @param array<Account> $accounts
     */
    private function displaySummary(SymfonyStyle $io, array $accounts, int $totalProcessed, int $totalMerged, float $startTime, bool $dryRun): void
    {
        $totalExecutionTime = microtime(true) - $startTime;

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
    }
}
