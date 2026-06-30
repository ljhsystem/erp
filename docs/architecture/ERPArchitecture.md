# ERP Architecture

## Future Policy Engine

- Status: deferred
- Decision date: 2026-06-26

### Decision

Policy Registry, Policy Loader, Policy Reader, Policy Helper, Policy Metadata, Policy Alias, Policy Normalizer, and Policy Validator were approved as an ERP-wide future architecture direction.

The design is retained as a standard architecture reference, but implementation is deferred.

### Why Deferred

Policy Registry is an ERP framework-level refactor.

Current priority is stabilizing core accounting workflows in this order:

1. Evidence
2. Processing Center
3. Transaction Management
4. Voucher Management
5. Ledger Management
6. Closing
7. Financial Statements

Framework refactoring will resume only after the business domains above are functionally stable.

### Current Runtime Rule

- Keep the current implementation structure.
- Keep `EvidenceTypePolicyService` as-is.
- Do not start Policy Registry implementation during the current accounting delivery phase.

### Resume Condition

Revisit Policy Registry only in the later framework refactoring phase, after the accounting core modules above are completed and stabilized.
