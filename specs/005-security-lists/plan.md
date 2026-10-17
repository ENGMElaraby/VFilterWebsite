# Implementation Plan: Security Lists

**Branch**: `005-security-lists` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

## Summary
This feature adds:
- Seven list pages that are edited as plain text, one value per line with `//` comments. A parser
  validates each line, reports errors by line number, and normalises CIDR ranges.
- Saves go through `ConfigStore` with only the changed entries, so they are audited and bump the
  `lists` counter once. A fingerprint check catches concurrent edits.
- An own-IP lockout warning on Allowed GM IPs.
- A Game Assistants grid (20 toggles per account).
- Net cafe special limits, stored as `cafe_limit` area rules.
- Shared contract 0.3.2 (PATCH) in both repos.

## Technical Context
**Language/Version**: PHP 8.5 · **Primary Dependencies**: Laravel 13, Filament 5
**Storage**: VFILTER (`filter_lists`, `filter_ga_permissions`, `filter_area_rules`); no migrations
**Testing**: Pest + Livewire · **Target**: as before · **Project Type**: Laravel admin panel
**Performance Goals**: save 10,000 lines < 5 s (SC-002) · **Constraints**: one change signal per save
**Scale/Scope**: lists ≤ 10,000 entries; assistants ≤ ~100

## Constitution Check
| Principle | Status | Notes |
| --- | --- | --- |
| I. Contract | Pass | 0.3.2 PATCH in both repos (list names, CIDR, cafe_limit). |
| II. DB-only | Pass | Only VFILTER config tables, via ConfigStore. |
| III. Security | Pass | Writes gated by `manage-filter-settings`, audited per entry, validated server-side. |
| IV. Test-first | Pass | Parser, editor, cafe limits, assistants and pages are tested first. |
| V. Catalog settings | N/A | Lists, not settings. |
| VI. Bilingual | Pass | EN/AR keys; RTL textarea uses `dir="auto"`. |
| VII. Simplicity | Pass | One generic list page + parser; no new tables. |

## Project Structure
```text
app/Lists/ListName.php, ListParser.php, ParsedList.php, IpRange.php, ListEditor.php, SaveResult.php,
          CafeLimits.php, GameAssistants.php, InvalidListException.php, ListChangedException.php
app/Filament/Pages/Security/SecurityListPage.php (abstract) + AllowedIps, ProxyBypassIps, ProxyBypassUsernames,
          BlockedIps, BlockedHwids, NetCafeIps, BlockedWords
resources/views/filament/pages/security-list.blade.php
app/Filament/Resources/GameAssistants/ (Resource, Pages/List, Tables)
app/Policies/FilterGaPermissionPolicy.php
lang/{en,ar}/panel.php · docs/shared-contract.md (+ ../VFilter)
tests/Feature/Lists/{ListParserTest,ListEditorTest,CafeLimitsTest,ListPagesTest}.php
tests/Feature/GameAssistants/{GameAssistantsTest,GameAssistantsPageTest}.php
```

## Complexity Tracking
None.
