<?php

declare(strict_types=1);

namespace CreditMergeBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class CreditMergeExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__.'/../Resources/config';
    }
}
