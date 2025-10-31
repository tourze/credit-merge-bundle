<?php

namespace CreditMergeBundle;

use CreditBundle\CreditBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\Symfony\CronJob\CronJobBundle;

class CreditMergeBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            CreditBundle::class => ['all' => true],
            CronJobBundle::class => ['all' => true],
        ];
    }
}
