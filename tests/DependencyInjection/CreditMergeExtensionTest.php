<?php

namespace CreditMergeBundle\Tests\DependencyInjection;

use CreditMergeBundle\DependencyInjection\CreditMergeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CreditMergeExtensionTest extends TestCase
{
    private CreditMergeExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new CreditMergeExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoadServicesConfiguration(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务是否被正确加载（使用完整的类名作为服务ID）
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Service\CreditMergeService'));
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Service\CreditMergeStatsService'));
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Service\TimeWindowService'));
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Service\CreditMergeOperationService'));
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Service\CreditSmallAmountsMergeService'));
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Service\MergePotentialAnalysisService'));
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Service\SmallAmountAnalysisService'));
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Command\MergeSmallAmountsCommand'));
    }

    public function testServicesAreAutoconfigured(): void
    {
        $this->extension->load([], $this->container);

        $services = [
            'CreditMergeBundle\Service\CreditMergeService',
            'CreditMergeBundle\Service\CreditMergeStatsService',
            'CreditMergeBundle\Service\TimeWindowService',
            'CreditMergeBundle\Service\CreditMergeOperationService',
            'CreditMergeBundle\Service\CreditSmallAmountsMergeService',
            'CreditMergeBundle\Service\MergePotentialAnalysisService',
            'CreditMergeBundle\Service\SmallAmountAnalysisService',
        ];

        foreach ($services as $serviceId) {
            $definition = $this->container->getDefinition($serviceId);
            $this->assertTrue($definition->isAutoconfigured(), "Service $serviceId should be autoconfigured");
        }
    }
}