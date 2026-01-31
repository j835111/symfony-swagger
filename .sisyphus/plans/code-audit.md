# Code Audit & Fix Plan (Symfony Swagger Bundle)

## TL;DR

> **Quick Summary**: comprehensive code audit using static analysis tools and manual review, followed by immediate remediation of discovered issues.
> 
> **Deliverables**:
> - Clean `composer analyze` output (PHPStan/CS-Fixer passing).
> - Audit Report (in `.sisyphus/audit_report.md`).
> - Refactored code addressing logic/architecture issues.
> 
> **Estimated Effort**: Medium
> **Parallel Execution**: Sequential (Audit -> Fix)
> **Critical Path**: Baseline -> Static Analysis Fixes -> Manual Review -> Logic Fixes

---

## Context

### Original Request
Perform a full code audit (correctness, compatibility, architecture) and provide a fix-it plan.

### Strategy
Since the specific bugs are unknown, this plan uses a **Discovery-Reaction** pattern:
1.  **Phase 1 (Automated)**: Run existing tools to find and fix deterministic issues.
2.  **Phase 2 (Manual)**: Agent reviews code against Symfony best practices.
3.  **Phase 3 (Remediation)**: Fix issues found in Phase 2.

---

## Work Objectives

### Core Objective
Ensure the bundle is bug-free, follows Symfony 6/7 standards, and passes strict static analysis.

### Definition of Done
- [ ] `composer analyze` returns exit code 0.
- [ ] No high-severity bugs found in manual review (or all fixed).
- [ ] Existing tests pass.

### Must Have
- [ ] PHPStan Level Max (or current config level) passing.
- [ ] PSR-12 / Symfony Coding Standards compliance.
- [ ] Backward Compatibility (no breaking public API changes without explicit user approval).

### Must NOT Have
- [ ] New dependencies (unless critical).
- [ ] Changes to `vendor/` directory.

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (`phpunit`, `phpstan`, `php-cs-fixer`).
- **User wants tests**: YES (Preserve and pass existing).
- **QA Approach**: Automated CI checks + Manual Agent Review.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Baseline & Auto-Fix):
└── Task 1: Baseline Analysis & Auto-Format

Wave 2 (Static Analysis):
└── Task 2: Fix PHPStan Issues

Wave 3 (Manual Audit):
├── Task 3: Review DependencyInjection & Config
├── Task 4: Review Service/Describer (Core Logic)
└── Task 5: Review Service/Analyzer & Attributes

Wave 4 (Remediation):
└── Task 6: Apply Logic & Architecture Fixes
```

---

## TODOs

- [ ] 1. Baseline Analysis & Auto-Format

  **What to do**:
  - Run `composer install` (if needed to get tools).
  - Run `composer analyze` to see current state.
  - Run `php-cs-fixer fix` to automatically clean up style.
  - Record initial state in `.sisyphus/audit_report.md`.

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`git-master`]

  **Acceptance Criteria**:
  - [ ] `php-cs-fixer` run completed.
  - [ ] `.sisyphus/audit_report.md` created with initial tool output.
  - [ ] Git commit if style changes applied.

- [ ] 2. Fix PHPStan Issues

  **What to do**:
  - Read PHPStan errors from Task 1 output.
  - Fix type hints, wrong return types, and logical errors detected by PHPStan.
  - Re-run `composer analyze` until passing.

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high` (requires type logic)
  - **Skills**: [`git-master`]

  **Acceptance Criteria**:
  - [ ] `phpstan analyse` returns exit code 0.
  - [ ] No `// @ignore` or `// @phpstan-ignore` added without documented justification.

- [ ] 3. Review DependencyInjection & Config

  **What to do**:
  - Read `src/DependencyInjection/*`.
  - Check for:
    - Deprecated Symfony config patterns.
    - Proper validation in `Configuration.php`.
    - Hardcoded values or insecure defaults.
  - Log findings to `.sisyphus/audit_report.md`.

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: [`git-master`]

  **Acceptance Criteria**:
  - [ ] Section "DependencyInjection Audit" added to report.
  - [ ] List of "Actionable Items" created.

- [ ] 4. Review Service/Describer (Core Logic)

  **What to do**:
  - Read `src/Service/Describer/*`.
  - Check for:
    - Complexity issues (nested loops/ifs).
    - OpenApi/Swagger specification compliance (are we generating valid specs?).
    - Error handling (does it crash on invalid routes?).
  - Log findings to `.sisyphus/audit_report.md`.

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: [`git-master`]

  **Acceptance Criteria**:
  - [ ] Section "Describer Audit" added to report.
  - [ ] List of "Actionable Items" created.

- [ ] 5. Review Service/Analyzer & Attributes

  **What to do**:
  - Read `src/Analyzer/*` and `src/Attribute/*`.
  - Check for:
    - PHP 8.1 Attribute usage correctness.
    - Reflection logic efficiency.
  - Log findings to `.sisyphus/audit_report.md`.

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: [`git-master`]

  **Acceptance Criteria**:
  - [ ] Section "Analyzer Audit" added to report.
  - [ ] List of "Actionable Items" created.

- [ ] 6. Apply Logic & Architecture Fixes

  **What to do**:
  - Read `.sisyphus/audit_report.md` for "Actionable Items" from Tasks 3, 4, 5.
  - Apply fixes for identified bugs and critical code smells.
  - Refactor complex methods if flagged.
  - **Constraint**: Do NOT break public API if possible.

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`git-master`]

  **Acceptance Criteria**:
  - [ ] All "Critical" and "High" severity items from report are marked FIXED.
  - [ ] Tests pass (`composer analyze` still passing).

---

## Success Criteria

### Verification Commands
```bash
composer analyze
# Expected: No errors

phpunit
# Expected: All tests pass (if tests exist)
```

### Final Checklist
- [ ] Code formatted (CS-Fixer).
- [ ] Static analysis passing (PHPStan).
- [ ] Audit Report generated.
- [ ] Critical logic issues fixed.
