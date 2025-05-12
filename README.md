# tourze/credit-merge-bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/credit-merge-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/credit-merge-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/packages%2Fcredit-merge-bundle%2F.github%2Fworkflows%2Fphpunit.yml?branch=main&style=flat-square)](https://github.com/tourze/php-monorepo/actions/workflows/packages/credit-merge-bundle/.github/workflows/phpunit.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/credit-merge-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/credit-merge-bundle)

A Symfony bundle for merging small credit transactions to optimize storage and processing. This helps in reducing the number of individual credit records, especially for accounts with frequent, low-value transactions.

## Features

- Merges small credit transactions for specified accounts.
- Supports merging records with no expiration date.
- Supports merging records with expiration dates based on configurable time window strategies (e.g., `daily`, `weekly`, `monthly`, `yearly`).
- Provides a console command (`credit:merge-small-amounts`) for manual or scheduled merging operations.
- Optionally triggers automatic merging of small amounts before large credit consumptions (configurable via environment variables).
- Offers detailed statistics on small credit amounts, including potential record reduction and merge efficiency.
- Configurable parameters such as minimum amount for merging, batch processing size, and time window strategies.
- Dry-run mode available for the console command to preview changes without actual execution.

## Installation

Require the bundle using Composer:

```bash
composer require tourze/credit-merge-bundle
```

Ensure the bundle is registered in your `config/bundles.php` if not done automatically by Symfony Flex:

```php
// config/bundles.php
return [
    // ...
    CreditMergeBundle\CreditMergeBundle::class => ['all' => true],
    // ...
];
```

## Configuration

The bundle offers several configuration options, primarily through environment variables for automatic merging and command-line options for manual merging.

**Environment Variables for Automatic Merging (used by `CreditSmallAmountsMergeService`):**

- `CREDIT_AUTO_MERGE_ENABLED`: (bool, default `true`) Enable/disable automatic merging before consumption.
- `CREDIT_AUTO_MERGE_THRESHOLD`: (int, default `100`) Number of records in consumption preview that triggers a merge check.
- `CREDIT_AUTO_MERGE_MIN_AMOUNT`: (float, default `100.0`) Minimum consumption amount to trigger an auto-merge check.
- `CREDIT_TIME_WINDOW_STRATEGY`: (string, default `monthly`) Default time window strategy for auto-merging. Options: `daily`, `weekly`, `monthly`, `yearly`.
- `CREDIT_MIN_AMOUNT_TO_MERGE`: (float, default `5.0`) Minimum amount for a record to be considered "small" during auto-merge.

**Console Command Options (`credit:merge-small-amounts`):**

- `account-id` (optional): Specific account ID to process. If not provided, processes all enabled accounts.
- `--min-amount` (`-m`): Minimum amount for a record to be considered "small" and eligible for merging (default: `5.0`).
- `--batch-size` (`-b`): Number of records to process in each batch (default: `100`).
- `--strategy` (`-s`): Time window strategy for merging records with expiry dates. Options: `daily`, `weekly`, `monthly`, `yearly` (default: `month`).
- `--dry-run`: Simulate the merge process without making actual changes.

The command is also configured as a cron task to run daily at 2 AM: `#[AsCronTask(expression: '0 2 * * *')]`.

## Quick Start

Here's how you can use the `CreditMergeService` programmatically or the console command.

**Using the `CreditMergeService`:**

```php
<?php

use CreditBundle\Entity\Account; // Your Account entity
use CreditMergeBundle\Service\CreditMergeService;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use Doctrine\ORM\EntityManagerInterface;

// Assuming $entityManager and $creditMergeService are available via Dependency Injection

/** @var EntityManagerInterface $entityManager */
/** @var CreditMergeService $creditMergeService */

// 1. Fetch an account
$accountRepository = $entityManager->getRepository(Account::class);
$account = $accountRepository->find(123); // Example: Account ID 123

if ($account) {
    $minAmountToMerge = 5.0; // Define what constitutes a "small" amount
    $strategy = TimeWindowStrategy::MONTH; // Merge based on monthly expiry windows

    // 2. Merge small amounts for the account
    $mergedCount = $creditMergeService->mergeSmallAmounts(
        $account,
        $minAmountToMerge,
        100, // batchSize
        $strategy
    );

    echo "Merged " . $mergedCount . " small credit records for account " . $account->getId() . ".\n";

    // 3. Get detailed statistics about small amounts
    $stats = $creditMergeService->getDetailedSmallAmountStats($account, $minAmountToMerge, $strategy);
    echo "Account " . $account->getId() . " has " . $stats->getCount() . " small records totaling " . $stats->getTotal() . " " . $account->getCurrency() . ".\n";
    if ($stats->hasMergeableRecords()) {
        echo "Potential record reduction: " . $stats->getPotentialRecordReduction() . " records (" . number_format($stats->getMergeEfficiency(), 2) . "% efficiency).\n";
        // You can also inspect $stats->getGroupStats() for detailed breakdown
    }
} else {
    echo "Account not found.\n";
}
```

**Using the Console Command:**

Merge small amounts for all accounts, using default settings (min amount 5.0, monthly strategy):

```bash
php bin/console credit:merge-small-amounts
```

Merge small amounts for a specific account (ID 123), with amounts less than 2.0, using a yearly strategy, in dry-run mode:

```bash
php bin/console credit:merge-small-amounts 123 --min-amount=2.0 --strategy=year --dry-run
```

To see all available options:

```bash
php bin/console credit:merge-small-amounts --help
```

## Workflow

For a detailed understanding of the merging logic and component interactions, please refer to the [WORKFLOW.md](WORKFLOW.md) document.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to this project.

## License

This bundle is released under the MIT License. Please see the [LICENSE](LICENSE) file for more information.
