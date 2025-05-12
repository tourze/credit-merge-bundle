# Credit Merge Bundle Workflow

This document outlines the workflows involved in the Credit Merge Bundle, focusing on how small credit transactions are identified and merged.

## Manual Merge Process (via `credit:merge-small-amounts` Command)

The `credit:merge-small-amounts` command allows for manual or scheduled merging of small credit transactions. Here's a simplified flow:

```mermaid
flowchart TD
    A[Start Command: credit:merge-small-amounts] --> B{Account ID Provided?};
    B -- Yes --> C[Fetch Specific Account];
    B -- No --> D[Fetch All Enabled Accounts];
    C --> E[Process Account];
    D --> F[Loop Through Accounts];
    F --> E;

    subgraph Process Account
        direction LR
        E1[Get Detailed Small Amount Stats] --> E2{Dry Run?};
        E2 -- Yes --> E3[Display Stats & Potential Merge Info];
        E2 -- No --> E4[Call CreditMergeService.mergeSmallAmounts];
        E4 --> E5[Log Results & Display Success/Error];
        E3 --> E6[End Account Processing];
        E5 --> E6;
    end

    E --> G{More Accounts?};
    G -- Yes --> F;
    G -- No --> H[End Command];
```

This diagram shows:

- The command can target a specific account or all enabled accounts.
- For each account, it first gathers statistics about small-value credits.
- If not in dry-run mode, it proceeds to merge these credits using `CreditMergeService`.
- The time window strategy, minimum amount, and batch size are passed as parameters.

## Automatic Merge Process (Triggered by Large Consumption - `CreditSmallAmountsMergeService`)

This service can be triggered before a significant credit consumption to proactively merge small amounts, potentially optimizing the consumption process.

```mermaid
flowchart TD
    AA[Large Credit Consumption Attempt] --> AB{Auto Merge Enabled? (`CREDIT_AUTO_MERGE_ENABLED`)};
    AB -- No --> AC[Proceed with Consumption];
    AB -- Yes --> AD{Cost Amount >= Min Auto Merge Amount? (`CREDIT_AUTO_MERGE_MIN_AMOUNT`)};
    AD -- No --> AC;
    AD -- Yes --> AE[Get Consumption Preview (TransactionRepository)];
    AE --> AF{Preview Needs Merge? (Record count > `CREDIT_AUTO_MERGE_THRESHOLD`)};
    AF -- No --> AC;
    AF -- Yes --> AG[Log Merge Start];
    AG --> AH[Execute Merge (TransactionRepository.mergeSmallAmounts)];
    AH --> AI[Log Merge Complete];
    AI --> AC;
```

This diagram illustrates:

- The automatic merge is conditional based on environment variable settings and the amount being consumed.
- It checks a preview of the consumption to see if the number of records involved exceeds a threshold.
- If conditions are met, it performs a merge operation before the main consumption proceeds.

## Core Merging Logic (`CreditMergeService.mergeSmallAmounts`)

This is the central service method responsible for the actual merging logic.

```mermaid
flowchart TD
    S1[Call mergeSmallAmounts(account, minAmount, batchSize, timeWindowStrategy)] --> S2[Start DB Transaction];
    S2 --> S3[Process No Expiry Records (CreditMergeOperationService.mergeNoExpiryRecords)];
    S3 --> S4[Process Expiry Records (CreditMergeOperationService.mergeExpiryRecords with strategy)];
    S4 --> S5[Commit DB Transaction];
    S5 --> S6[Log Completion & Return Merge Count];
    S2 -- On Exception --> S7[Rollback DB Transaction];
    S7 --> S8[Log Error & Rethrow];
```

This diagram highlights:

- The operation is transactional.
- It separately handles records with no expiration and those with expiration dates, applying the specified time window strategy for the latter.

## Component Interaction (Simplified)

```mermaid
sequenceDiagram
    participant Cmd as MergeSmallAmountsCommand
    participant CMS as CreditMergeService
    participant COS as CreditMergeOperationService
    participant EM as EntityManager
    participant Logger as PSR Logger

    Cmd->>CMS: mergeSmallAmounts(account, minAmount, strategy)
    CMS->>Logger: info("Start merging")
    CMS->>EM: beginTransaction()
    CMS->>COS: mergeNoExpiryRecords(account, minAmount)
    COS->>EM: find/update/create records
    COS-->>CMS: count1
    CMS->>COS: mergeExpiryRecords(account, minAmount, strategy)
    COS->>EM: find/update/create records (grouped by time window)
    COS-->>CMS: count2
    CMS->>EM: commit()
    CMS->>Logger: info("Merge complete")
    CMS-->>Cmd: totalMergedCount

    alt On Error
        CMS->>EM: rollBack()
        CMS->>Logger: error("Merge failed")
        CMS-->>Cmd: throws Exception
    end
```

This sequence shows the interaction between the command, the main merge service, the operation service (which likely handles the direct database interactions or repository calls), the entity manager for transactions, and the logger.
