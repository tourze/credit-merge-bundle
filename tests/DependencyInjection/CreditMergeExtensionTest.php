<?php

namespace CreditMergeBundle\Tests\DependencyInjection;

use CreditMergeBundle\DependencyInjection\CreditMergeExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(CreditMergeExtension::class)]
final class CreditMergeExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    // DependencyInjection Extension 不是服务，可以直接实例化
    // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass

    private CreditMergeExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new CreditMergeExtension();
        $this->container = new ContainerBuilder();

        // Set required parameters for AutoExtension
        $this->container->setParameter('kernel.environment', 'test');
        $this->container->setParameter('kernel.debug', true);
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->container->setParameter('kernel.logs_dir', sys_get_temp_dir());
        $this->container->setParameter('kernel.project_dir', __DIR__.'/../../');
    }

    public function testLoadLoadsServicesYaml(): void
    {
        $this->extension->load([], $this->container);

        // Check that services are loaded by verifying some key services exist
        $this->assertTrue($this->container->hasDefinition('CreditMergeBundle\Command\MergeSmallAmountsCommand'));
    }

    public function testLoadWithEmptyConfigs(): void
    {
        $this->extension->load([], $this->container);

        $this->assertGreaterThan(0, count($this->container->getDefinitions()));
    }

    public function testLoadDoesNotThrowException(): void
    {
        $this->expectNotToPerformAssertions();

        $this->extension->load([], $this->container);
        $this->extension->load([['key' => 'value']], $this->container);
        $this->extension->load([[], ['another' => 'config']], $this->container);
    }
}
