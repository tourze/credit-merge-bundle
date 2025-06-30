<?php

namespace CreditMergeBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Model\ConsumptionPreview;
use CreditBundle\Repository\TransactionRepository;
use CreditMergeBundle\Service\CreditSmallAmountsMergeService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreditSmallAmountsMergeServiceTest extends TestCase
{
    private CreditSmallAmountsMergeService $service;
    private TransactionRepository&MockObject $transactionRepository;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new CreditSmallAmountsMergeService(
            $this->transactionRepository,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        // 清理环境变量
        unset($_ENV['CREDIT_AUTO_MERGE_ENABLED']);
        unset($_ENV['CREDIT_AUTO_MERGE_THRESHOLD']);
        unset($_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT']);
        unset($_ENV['CREDIT_TIME_WINDOW_STRATEGY']);
        unset($_ENV['CREDIT_MIN_AMOUNT_TO_MERGE']);
    }

    public function testCheckAndMergeIfNeededWithAutoMergeDisabled(): void
    {
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '0';

        $account = $this->createMock(Account::class);

        $this->transactionRepository->expects($this->never())->method('getConsumptionPreview');
        $this->logger->expects($this->never())->method('info');

        $this->service->checkAndMergeIfNeeded($account, 1000.0);
    }

    public function testCheckAndMergeIfNeededWithCostAmountBelowThreshold(): void
    {
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = '200.0';

        $account = $this->createMock(Account::class);

        $this->transactionRepository->expects($this->never())->method('getConsumptionPreview');
        $this->logger->expects($this->never())->method('info');

        $this->service->checkAndMergeIfNeeded($account, 100.0);
    }

    public function testCheckAndMergeIfNeededWhenNoMergeRequired(): void
    {
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = '50.0';
        $_ENV['CREDIT_AUTO_MERGE_THRESHOLD'] = '100';

        $account = $this->createMock(Account::class);

        $preview = $this->createMock(ConsumptionPreview::class);
        $preview->method('needsMerge')->willReturn(false);

        $this->transactionRepository->expects($this->once())
            ->method('getConsumptionPreview')
            ->with($account, 100.0, 100)
            ->willReturn($preview);

        $this->logger->expects($this->never())->method('info');

        $this->service->checkAndMergeIfNeeded($account, 100.0);
    }

    public function testCheckAndMergeIfNeededWithMergeRequired(): void
    {
        $_ENV['CREDIT_AUTO_MERGE_ENABLED'] = '1';
        $_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT'] = '50.0';
        $_ENV['CREDIT_AUTO_MERGE_THRESHOLD'] = '100';
        $_ENV['CREDIT_TIME_WINDOW_STRATEGY'] = 'monthly';

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(123);

        $preview = $this->createMock(ConsumptionPreview::class);
        $preview->method('needsMerge')->willReturn(true);
        $preview->method('getRecordCount')->willReturn(150);

        $this->transactionRepository->expects($this->once())
            ->method('getConsumptionPreview')
            ->with($account, 200.0, 100)
            ->willReturn($preview);

        // 验证日志记录
        $this->logger->expects($this->exactly(2))->method('info')
            ->willReturnCallback(function ($message, $context) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 1) {
                    $this->assertEquals('大额消费触发小额积分合并', $message);
                    $this->assertEquals([
                        'account' => 123,
                        'costAmount' => 200.0,
                        'recordCount' => 150,
                        'threshold' => 100,
                        'strategy' => 'monthly'
                    ], $context);
                } elseif ($callCount === 2) {
                    $this->assertEquals('小额积分合并完成', $message);
                    $this->assertEquals([
                        'account' => 123,
                        'mergeCount' => 0, // 因为方法还未实现，返回0
                        'strategy' => 'monthly'
                    ], $context);
                }
            });

        $this->service->checkAndMergeIfNeeded($account, 200.0);
    }

    public function testCheckAndMergeIfNeededWithDefaultValues(): void
    {
        // 测试使用默认值的情况
        unset($_ENV['CREDIT_AUTO_MERGE_ENABLED']);
        unset($_ENV['CREDIT_AUTO_MERGE_THRESHOLD']);
        unset($_ENV['CREDIT_AUTO_MERGE_MIN_AMOUNT']);
        unset($_ENV['CREDIT_TIME_WINDOW_STRATEGY']);

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(123);

        $preview = $this->createMock(ConsumptionPreview::class);
        $preview->method('needsMerge')->willReturn(true);
        $preview->method('getRecordCount')->willReturn(150);

        $this->transactionRepository->expects($this->once())
            ->method('getConsumptionPreview')
            ->with($account, 100.0, 100) // 默认阈值是100
            ->willReturn($preview);

        $this->logger->expects($this->exactly(2))->method('info');

        $this->service->checkAndMergeIfNeeded($account, 100.0); // 默认最小金额是100.0
    }
}