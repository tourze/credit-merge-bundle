<?php

namespace CreditMergeBundle\Tests\Service;

use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Entity\MergeStatistics;
use CreditMergeBundle\Service\AdminMenu;
use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private AdminMenu $service;

    private LinkGeneratorInterface&MockObject $mockLinkGenerator;

    protected function onSetUp(): void
    {
        $this->mockLinkGenerator = $this->createMock(LinkGeneratorInterface::class);
        self::getContainer()->set(LinkGeneratorInterface::class, $this->mockLinkGenerator);
        $this->service = self::getService(AdminMenu::class);
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(AdminMenu::class, $this->service);
    }

    /**
     * 测试在已存在积分中心菜单项时添加子菜单.
     */
    public function testInvokeWithExistingCreditCenterMenu(): void
    {
        // 准备模拟数据
        $mockMainItem = $this->createMock(ItemInterface::class);
        $mockCreditCenter = $this->createMock(ItemInterface::class);
        $mockMergeOperationChild = $this->createMock(ItemInterface::class);
        $mockMergeStatisticsChild = $this->createMock(ItemInterface::class);

        // 配置期望的方法调用
        $mockMainItem->expects($this->once())
            ->method('getChild')
            ->with('积分中心')
            ->willReturn($mockCreditCenter)
        ;

        $this->mockLinkGenerator->expects($this->exactly(2))
            ->method('getCurdListPage')
            ->willReturnMap([
                [MergeOperation::class, '/admin/merge-operation'],
                [MergeStatistics::class, '/admin/merge-statistics'],
            ])
        ;

        $mockCreditCenter->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function ($name) use ($mockMergeOperationChild, $mockMergeStatisticsChild) {
                if ('合并操作记录' === $name) {
                    return $mockMergeOperationChild;
                }
                if ('合并统计数据' === $name) {
                    return $mockMergeStatisticsChild;
                }

                return $this->createMock(ItemInterface::class);
            })
        ;

        $mockMergeOperationChild->expects($this->once())
            ->method('setUri')
            ->with('/admin/merge-operation')
            ->willReturnSelf()
        ;

        $mockMergeStatisticsChild->expects($this->once())
            ->method('setUri')
            ->with('/admin/merge-statistics')
            ->willReturnSelf()
        ;

        // 执行测试
        $this->service->__invoke($mockMainItem);
    }

    /**
     * 测试在不存在积分中心菜单项时创建并添加子菜单.
     */
    public function testInvokeWithoutExistingCreditCenterMenu(): void
    {
        // 准备模拟数据
        $mockMainItem = $this->createMock(ItemInterface::class);
        $mockCreditCenter = $this->createMock(ItemInterface::class);
        $mockMergeOperationChild = $this->createMock(ItemInterface::class);
        $mockMergeStatisticsChild = $this->createMock(ItemInterface::class);

        // 配置期望的方法调用
        $mockMainItem->expects($this->once())
            ->method('getChild')
            ->with('积分中心')
            ->willReturn(null)
        ;

        $mockMainItem->expects($this->once())
            ->method('addChild')
            ->with('积分中心')
            ->willReturn($mockCreditCenter)
        ;

        $this->mockLinkGenerator->expects($this->exactly(2))
            ->method('getCurdListPage')
            ->willReturnMap([
                [MergeOperation::class, '/admin/merge-operation'],
                [MergeStatistics::class, '/admin/merge-statistics'],
            ])
        ;

        $mockCreditCenter->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function ($name) use ($mockMergeOperationChild, $mockMergeStatisticsChild) {
                if ('合并操作记录' === $name) {
                    return $mockMergeOperationChild;
                }
                if ('合并统计数据' === $name) {
                    return $mockMergeStatisticsChild;
                }

                return $this->createMock(ItemInterface::class);
            })
        ;

        $mockMergeOperationChild->expects($this->once())
            ->method('setUri')
            ->with('/admin/merge-operation')
            ->willReturnSelf()
        ;

        $mockMergeStatisticsChild->expects($this->once())
            ->method('setUri')
            ->with('/admin/merge-statistics')
            ->willReturnSelf()
        ;

        // 执行测试
        $this->service->__invoke($mockMainItem);
    }

    /**
     * 测试LinkGenerator调用正确的实体类.
     */
    public function testLinkGeneratorCalledWithCorrectEntities(): void
    {
        $mockMainItem = $this->createMock(ItemInterface::class);
        $mockCreditCenter = $this->createMock(ItemInterface::class);
        $mockChild = $this->createMock(ItemInterface::class);

        $mockMainItem->method('getChild')->willReturn($mockCreditCenter);
        $mockCreditCenter->method('addChild')->willReturn($mockChild);
        $mockChild->method('setUri')->willReturnSelf();

        // 验证LinkGenerator被正确调用
        $this->mockLinkGenerator->expects($this->exactly(2))
            ->method('getCurdListPage')
            ->willReturnCallback(function (string $entityClass) {
                $this->assertContains($entityClass, [MergeOperation::class, MergeStatistics::class]);

                return '/test-url';
            });

        $this->service->__invoke($mockMainItem);
    }
}
