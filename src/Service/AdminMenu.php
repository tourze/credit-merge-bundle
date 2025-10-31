<?php

declare(strict_types=1);

namespace CreditMergeBundle\Service;

use CreditMergeBundle\Entity\MergeOperation;
use CreditMergeBundle\Entity\MergeStatistics;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

#[Autoconfigure(public: true)]
class AdminMenu implements MenuProviderInterface
{
    public function __construct(private LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        $creditCenter = $item->getChild('积分中心');
        if (null === $creditCenter) {
            $creditCenter = $item->addChild('积分中心');
        }

        // 积分合并管理分组
        $creditCenter->addChild('合并操作记录')->setUri($this->linkGenerator->getCurdListPage(MergeOperation::class));
        $creditCenter->addChild('合并统计数据')->setUri($this->linkGenerator->getCurdListPage(MergeStatistics::class));
    }
}
