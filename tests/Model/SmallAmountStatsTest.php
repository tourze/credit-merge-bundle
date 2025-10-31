<?php

namespace CreditMergeBundle\Tests\Model;

use CreditMergeBundle\Enum\TimeWindowStrategy;
use CreditMergeBundle\Model\SmallAmountStats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SmallAmountStats::class)]
final class SmallAmountStatsTest extends TestCase
{
    protected function onSetUp(): void
    {
        // 模型测试不需要特殊设置
    }

    public function testModelClassExists(): void
    {
        // 验证模型类存在且可加载
        $reflection = new \ReflectionClass(SmallAmountStats::class);
        $this->assertTrue($reflection->isInstantiable());
    }

    public function testModelImplementsJsonSerializable(): void
    {
        // 验证模型实现了 JsonSerializable 接口
        $this->assertContains(\JsonSerializable::class, class_implements(SmallAmountStats::class));
    }

    public function testModelHasRequiredMethods(): void
    {
        // 验证关键方法存在
        $requiredMethods = [
            'getAccount',
            'getCount',
            'getTotal',
            'getThreshold',
            'getStrategy',
            'hasMergeableRecords',
            'getPotentialRecordReduction',
            'getMergeEfficiency',
            'getAverageAmount',
            'addGroupStats',
            'getGroupStats',
            'jsonSerialize',
            'createEmpty',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists(SmallAmountStats::class, $method));
        }
    }

    public function testAddGroupStats(): void
    {
        // 构造最小数据
        $account = new \CreditBundle\Entity\Account();
        $account->setName('acc');
        $account->setCurrency('CNY');

        $stats = new SmallAmountStats($account, 3, 6.0, 5.0, TimeWindowStrategy::DAY);

        $dt = new \DateTimeImmutable('2024-01-15 10:00:00');
        $stats->addGroupStats('2024-01-15', 2, 4.0, $dt);

        $groups = $stats->getGroupStats();
        $this->assertArrayHasKey('2024-01-15', $groups);
        $this->assertSame(2, $groups['2024-01-15']['count']);
        $this->assertSame(4.0, $groups['2024-01-15']['total']);
        $this->assertSame($dt->format('Y-m-d H:i:s'), $groups['2024-01-15']['earliest_expiry']);
    }

    public function testJsonSerialize(): void
    {
        $account = new \CreditBundle\Entity\Account();
        $account->setName('acc');
        $account->setCurrency('CNY');
        $stats = new SmallAmountStats($account, 2, 6.0, 5.0, TimeWindowStrategy::DAY);
        $json = $stats->jsonSerialize();
        $this->assertArrayHasKey('group_stats', $json);
        $this->assertArrayHasKey('has_mergeable_records', $json);
        $this->assertTrue($json['has_mergeable_records']);
    }

    public function testTimeWindowStrategyEnumExists(): void
    {
        // 验证时间窗口策略枚举存在
        $this->assertTrue(enum_exists(TimeWindowStrategy::class));
    }

    public function testTimeWindowStrategyHasRequiredCases(): void
    {
        // 验证枚举包含必需的值
        $reflection = new \ReflectionEnum(TimeWindowStrategy::class);
        $cases = $reflection->getCases();

        $caseNames = array_map(fn ($case) => $case->getName(), $cases);
        $this->assertContains('MONTH', $caseNames);
        $this->assertContains('DAY', $caseNames);
        $this->assertContains('WEEK', $caseNames);
        $this->assertContains('ALL', $caseNames);
    }

    public function testModelConstructorParameters(): void
    {
        // 验证构造函数参数
        $reflection = new \ReflectionClass(SmallAmountStats::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        $parameters = $constructor->getParameters();
        $this->assertCount(5, $parameters);

        $paramNames = array_map(fn ($param) => $param->getName(), $parameters);
        $this->assertContains('account', $paramNames);
        $this->assertContains('count', $paramNames);
        $this->assertContains('total', $paramNames);
        $this->assertContains('threshold', $paramNames);
        $this->assertContains('strategy', $paramNames);
    }
}
