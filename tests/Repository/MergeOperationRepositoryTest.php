<?php

declare(strict_types=1);

namespace CreditMergeBundle\Tests\Repository;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Repository\MergeOperationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * 合并操作记录仓库测试.
 *
 * @internal
 */
#[CoversClass(MergeOperationRepository::class)]
#[RunTestsInSeparateProcesses]
final class MergeOperationRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Create test fixture data to satisfy the countWithDataFixture test
        // This is needed because DataFixtures are not being loaded automatically
        $account = new Account();
        $account->setName('fixture-account');
        $account->setCurrency('CNY');

        $mergeOperation = new MergeOperation();
        $mergeOperation->setAccount($account);
        $mergeOperation->setTimeWindowStrategy(TimeWindowStrategy::DAY);
        $mergeOperation->setMinAmountThreshold('5.00');
        $mergeOperation->setRecordsCountBefore(10);
        $mergeOperation->setRecordsCountAfter(6);
        $mergeOperation->setMergedRecordsCount(4);
        $mergeOperation->setTotalAmount('20.00');
        $mergeOperation->setBatchSize(100);
        $mergeOperation->setIsDryRun(false);
        $mergeOperation->setStatus('success');
        $mergeOperation->setOperationTime(new \DateTimeImmutable());

        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($mergeOperation);
        self::getEntityManager()->flush();
    }

    protected function createNewEntity(): object
    {
        $account = new Account();
        $account->setName('test-account-'.uniqid());
        $account->setCurrency('CNY');

        $mergeOperation = new MergeOperation();
        $mergeOperation->setAccount($account);
        $mergeOperation->setTimeWindowStrategy(TimeWindowStrategy::DAY);
        $mergeOperation->setMinAmountThreshold('5.00');
        $mergeOperation->setRecordsCountBefore(10);
        $mergeOperation->setRecordsCountAfter(6);
        $mergeOperation->setMergedRecordsCount(4);
        $mergeOperation->setTotalAmount('20.00');
        $mergeOperation->setBatchSize(100);
        $mergeOperation->setIsDryRun(false);
        $mergeOperation->setStatus('success');

        // 手动持久化 Account 实体以解决 cascade 问题
        self::getEntityManager()->persist($account);

        return $mergeOperation;
    }

    protected function getRepository(): MergeOperationRepository
    {
        return self::getService(MergeOperationRepository::class);
    }

    public function testFindByAccount(): void
    {
        $account = new Account();
        $account->setName('test-find-account');
        $account->setCurrency('CNY');

        $mergeOperation = new MergeOperation();
        $mergeOperation->setAccount($account);
        $mergeOperation->setTimeWindowStrategy(TimeWindowStrategy::MONTH);
        $mergeOperation->setMinAmountThreshold('10.00');
        $mergeOperation->setRecordsCountBefore(20);
        $mergeOperation->setRecordsCountAfter(11);
        $mergeOperation->setMergedRecordsCount(9);
        $mergeOperation->setTotalAmount('90.00');
        $mergeOperation->setBatchSize(50);
        $mergeOperation->setIsDryRun(false);
        $mergeOperation->setStatus('success');

        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($mergeOperation);
        self::getEntityManager()->flush();

        $foundOperations = $this->getRepository()->findBy(['account' => $account]);
        $this->assertCount(1, $foundOperations);
        $this->assertSame($mergeOperation, $foundOperations[0]);
    }

    public function testFindByStatus(): void
    {
        $account = new Account();
        $account->setName('test-status-account');
        $account->setCurrency('CNY');

        $mergeOperation1 = new MergeOperation();
        $mergeOperation1->setAccount($account);
        $mergeOperation1->setTimeWindowStrategy(TimeWindowStrategy::WEEK);
        $mergeOperation1->setMinAmountThreshold('5.00');
        $mergeOperation1->setRecordsCountBefore(15);
        $mergeOperation1->setRecordsCountAfter(8);
        $mergeOperation1->setMergedRecordsCount(7);
        $mergeOperation1->setTotalAmount('35.00');
        $mergeOperation1->setBatchSize(25);
        $mergeOperation1->setIsDryRun(false);
        $mergeOperation1->setStatus('pending');

        $mergeOperation2 = new MergeOperation();
        $mergeOperation2->setAccount($account);
        $mergeOperation2->setTimeWindowStrategy(TimeWindowStrategy::ALL);
        $mergeOperation2->setMinAmountThreshold('5.00');
        $mergeOperation2->setRecordsCountBefore(12);
        $mergeOperation2->setRecordsCountAfter(7);
        $mergeOperation2->setMergedRecordsCount(5);
        $mergeOperation2->setTotalAmount('25.00');
        $mergeOperation2->setBatchSize(30);
        $mergeOperation2->setIsDryRun(false);
        $mergeOperation2->setStatus('pending');

        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($mergeOperation1);
        self::getEntityManager()->persist($mergeOperation2);
        self::getEntityManager()->flush();

        $pendingOperations = $this->getRepository()->findBy(['status' => 'pending']);
        $this->assertGreaterThanOrEqual(2, count($pendingOperations));
    }

    public function testFindByTimeWindowStrategy(): void
    {
        $account = new Account();
        $account->setName('test-strategy-account');
        $account->setCurrency('CNY');

        $mergeOperation = new MergeOperation();
        $mergeOperation->setAccount($account);
        $mergeOperation->setTimeWindowStrategy(TimeWindowStrategy::DAY);
        $mergeOperation->setMinAmountThreshold('3.00');
        $mergeOperation->setRecordsCountBefore(8);
        $mergeOperation->setRecordsCountAfter(5);
        $mergeOperation->setMergedRecordsCount(3);
        $mergeOperation->setTotalAmount('15.00');
        $mergeOperation->setBatchSize(20);
        $mergeOperation->setIsDryRun(true);
        $mergeOperation->setStatus('success');

        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($mergeOperation);
        self::getEntityManager()->flush();

        $dayOperations = $this->getRepository()->findBy(['timeWindowStrategy' => TimeWindowStrategy::DAY]);
        $this->assertGreaterThanOrEqual(1, count($dayOperations));
    }

    public function testFindRecentOperations(): void
    {
        $account = new Account();
        $account->setName('test-recent-account');
        $account->setCurrency('CNY');

        $mergeOperation = new MergeOperation();
        $mergeOperation->setAccount($account);
        $mergeOperation->setTimeWindowStrategy(TimeWindowStrategy::MONTH);
        $mergeOperation->setMinAmountThreshold('8.00');
        $mergeOperation->setRecordsCountBefore(25);
        $mergeOperation->setRecordsCountAfter(13);
        $mergeOperation->setMergedRecordsCount(12);
        $mergeOperation->setTotalAmount('96.00');
        $mergeOperation->setBatchSize(40);
        $mergeOperation->setIsDryRun(false);
        $mergeOperation->setStatus('success');
        $mergeOperation->setOperationTime(new \DateTimeImmutable('-1 hour'));

        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($mergeOperation);
        self::getEntityManager()->flush();

        // Test that we can find operations created recently
        $recentOperations = $this->getRepository()->findBy(
            [],
            ['operationTime' => 'DESC'],
            10
        );

        $this->assertGreaterThan(0, count($recentOperations));
        $this->assertInstanceOf(MergeOperation::class, $recentOperations[0]);
    }

    public function testFindByTimeRange(): void
    {
        $account = new Account();
        $account->setName('range-account');
        $account->setCurrency('CNY');

        $op = new MergeOperation();
        $op->setAccount($account);
        $op->setTimeWindowStrategy(TimeWindowStrategy::WEEK);
        $op->setMinAmountThreshold('5.00');
        $op->setRecordsCountBefore(10);
        $op->setRecordsCountAfter(9);
        $op->setMergedRecordsCount(1);
        $op->setTotalAmount('5.00');
        $op->setBatchSize(10);
        $op->setIsDryRun(false);
        $op->setStatus('success');
        $op->setOperationTime(new \DateTimeImmutable('-10 minutes'));

        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($op);
        self::getEntityManager()->flush();

        $from = new \DateTimeImmutable('-1 hour');
        $to = new \DateTimeImmutable('now');
        $found = $this->getRepository()->findByTimeRange($from, $to);
        $this->assertNotEmpty($found);
        $this->assertInstanceOf(MergeOperation::class, $found[0]);
    }

    public function testFindLatestByAccount(): void
    {
        $account = new Account();
        $account->setName('latest-account');
        $account->setCurrency('CNY');

        $op1 = new MergeOperation();
        $op1->setAccount($account);
        $op1->setTimeWindowStrategy(TimeWindowStrategy::DAY);
        $op1->setMinAmountThreshold('1.00');
        $op1->setRecordsCountBefore(2);
        $op1->setRecordsCountAfter(1);
        $op1->setMergedRecordsCount(1);
        $op1->setTotalAmount('2.00');
        $op1->setBatchSize(10);
        $op1->setIsDryRun(false);
        $op1->setStatus('success');
        $op1->setOperationTime(new \DateTimeImmutable('-2 hours'));

        $op2 = clone $op1;
        $op2->setOperationTime(new \DateTimeImmutable('-1 minute'));

        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($op1);
        self::getEntityManager()->persist($op2);
        self::getEntityManager()->flush();

        $latest = $this->getRepository()->findLatestByAccount($account);
        $this->assertInstanceOf(MergeOperation::class, $latest);
        $this->assertSame($op2->getOperationTime()->format(DATE_ATOM), $latest->getOperationTime()->format(DATE_ATOM));
    }

    public function testFindByCriteria(): void
    {
        $account = new Account();
        $account->setName('criteria-account');
        $account->setCurrency('CNY');

        $op = new MergeOperation();
        $op->setAccount($account);
        $op->setTimeWindowStrategy(TimeWindowStrategy::MONTH);
        $op->setMinAmountThreshold('10.00');
        $op->setRecordsCountBefore(10);
        $op->setRecordsCountAfter(9);
        $op->setMergedRecordsCount(1);
        $op->setTotalAmount('10.00');
        $op->setBatchSize(10);
        $op->setIsDryRun(true);
        $op->setStatus('pending');
        $op->setOperationTime(new \DateTimeImmutable());

        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($op);
        self::getEntityManager()->flush();

        $found = $this->getRepository()->findByCriteria([
            'account' => $account,
            'status' => 'pending',
            'isDryRun' => true,
            'timeWindowStrategy' => TimeWindowStrategy::MONTH,
        ]);

        $this->assertNotEmpty($found);
        $this->assertSame('pending', $found[0]->getStatus());
    }
}
