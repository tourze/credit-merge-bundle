<?php

declare(strict_types=1);

namespace CreditMergeBundle\Tests;

use CreditMergeBundle\CreditMergeBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CreditMergeBundle::class)]
#[RunTestsInSeparateProcesses]
final class CreditMergeBundleTest extends AbstractBundleTestCase
{
}
