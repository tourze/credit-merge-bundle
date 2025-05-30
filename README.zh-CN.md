# tourze/credit-merge-bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/credit-merge-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/credit-merge-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/packages%2Fcredit-merge-bundle%2F.github%2Fworkflows%2Fphpunit.yml?branch=main&style=flat-square)](https://github.com/tourze/php-monorepo/actions/workflows/packages/credit-merge-bundle/.github/workflows/phpunit.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/credit-merge-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/credit-merge-bundle)

一个用于合并小额信用积分交易的 Symfony Bundle，旨在优化存储和处理。这有助于减少单个信用积分记录的数量，特别是对于那些拥有频繁、低价值交易的账户。

## 功能特性

- 合并指定账户的小额信用积分交易。
- 支持合并无过期日期的记录。
- 支持根据可配置的时间窗口策略（例如 `daily`, `weekly`, `monthly`, `yearly`）合并有过期日期的记录。
- 提供控制台命令 (`credit:merge-small-amounts`) 用于手动或计划性的合并操作。
- 在大额信用积分消费前可选地触发小额积分的自动合并（可通过环境变量配置）。
- 提供关于小额信用积分的详细统计信息，包括潜在的记录减少数量和合并效率。
- 可配置参数，如合并的最小金额、批量处理大小和时间窗口策略。
- 控制台命令支持试运行模式（dry-run），以便在不实际执行更改的情况下预览更改。

## 安装说明

使用 Composer 安装此 Bundle：

```bash
composer require tourze/credit-merge-bundle
```

如果 Symfony Flex 未自动完成，请确保在您的 `config/bundles.php` 文件中注册此 Bundle：

```php
// config/bundles.php
return [
    // ...
    CreditMergeBundle\CreditMergeBundle::class => ['all' => true],
    // ...
];
```

## 配置说明

该 Bundle 提供多种配置选项，主要通过环境变量进行自动合并配置，通过命令行选项进行手动合并配置。

**自动合并环境变量 (供 `CreditSmallAmountsMergeService` 使用):**

- `CREDIT_AUTO_MERGE_ENABLED`: (布尔型, 默认为 `true`) 启用/禁用消费前的自动合并。
- `CREDIT_AUTO_MERGE_THRESHOLD`: (整型, 默认为 `100`) 触发合并检查的消费预览记录数。
- `CREDIT_AUTO_MERGE_MIN_AMOUNT`: (浮点型, 默认为 `100.0`) 触发自动合并检查的最小消费金额。
- `CREDIT_TIME_WINDOW_STRATEGY`: (字符串, 默认为 `monthly`) 自动合并的默认时间窗口策略。可选值: `daily`, `weekly`, `monthly`, `yearly`。
- `CREDIT_MIN_AMOUNT_TO_MERGE`: (浮点型, 默认为 `5.0`) 自动合并期间，记录被视为“小额”的最小金额。

**控制台命令选项 (`credit:merge-small-amounts`):**

- `account-id` (可选): 要处理的特定账户 ID。如果未提供，则处理所有启用的账户。
- `--min-amount` (`-m`): 记录被视为“小额”并符合合并条件的最小金额 (默认为 `5.0`)。
- `--batch-size` (`-b`): 每批处理的记录数 (默认为 `100`)。
- `--strategy` (`-s`): 合并带过期日期记录的时间窗口策略。可选值: `daily`, `weekly`, `monthly`, `yearly` (默认为 `month`)。
- `--dry-run`: 模拟合并过程，不进行实际更改。

该命令也被配置为 Cron 任务，每天凌晨2点执行: `#[AsCronTask(expression: '0 2 * * *')]`。

## 快速开始

以下是如何以编程方式使用 `CreditMergeService` 或控制台命令的示例。

**使用 `CreditMergeService`:**

```php
<?php

use CreditBundle\Entity\Account; // 您的账户实体
use CreditMergeBundle\Service\CreditMergeService;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use Doctrine\ORM\EntityManagerInterface;

// 假设 $entityManager 和 $creditMergeService 可通过依赖注入获得

/** @var EntityManagerInterface $entityManager */
/** @var CreditMergeService $creditMergeService */

// 1. 获取一个账户
$accountRepository = $entityManager->getRepository(Account::class);
$account = $accountRepository->find(123); // 示例：账户 ID 123

if ($account) {
    $minAmountToMerge = 5.0; // 定义什么构成“小额”金额
    $strategy = TimeWindowStrategy::MONTH; // 基于月度过期窗口进行合并

    // 2. 合并账户的小额积分
    $mergedCount = $creditMergeService->mergeSmallAmounts(
        $account,
        $minAmountToMerge,
        100, // batchSize
        $strategy
    );

    echo "为账户 " . $account->getId() . " 合并了 " . $mergedCount . " 条小额信用积分记录。\n";

    // 3. 获取关于小额积分的详细统计信息
    $stats = $creditMergeService->getDetailedSmallAmountStats($account, $minAmountToMerge, $strategy);
    echo "账户 " . $account->getId() . " 有 " . $stats->getCount() . " 条小额记录，总计 " . $stats->getTotal() . " " . $account->getCurrency() . "。\n";
    if ($stats->hasMergeableRecords()) {
        echo "潜在记录减少数量: " . $stats->getPotentialRecordReduction() . " 条记录 (" . number_format($stats->getMergeEfficiency(), 2) . "% 效率)。\n";
        // 您还可以检查 $stats->getGroupStats() 以获取详细分类
    }
} else {
    echo "未找到账户。\n";
}
```

**使用控制台命令:**

为所有账户合并小额积分，使用默认设置 (最小金额 5.0，月度策略):

```bash
php bin/console credit:merge-small-amounts
```

为特定账户 (ID 123) 合并小额积分，金额小于 2.0，使用年度策略，并以试运行模式执行:

```bash
php bin/console credit:merge-small-amounts 123 --min-amount=2.0 --strategy=year --dry-run
```

查看所有可用选项:

```bash
php bin/console credit:merge-small-amounts --help
```

## 工作流程

要详细了解合并逻辑和组件交互，请参阅 [WORKFLOW.md](WORKFLOW.md) 文档。

## 贡献指南

有关如何为此项目做出贡献的详细信息，请参阅 [CONTRIBUTING.md](CONTRIBUTING.md)。

## 版权和许可

此 Bundle 在 MIT 许可下发布。有关更多信息，请参阅 [LICENSE](LICENSE) 文件。
