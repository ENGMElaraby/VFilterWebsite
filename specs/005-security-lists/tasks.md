---
description: "Tasks for 005 Security Lists"
---

# Tasks: Security Lists

**Input**: `specs/005-security-lists/` · **Tests**: required (constitution IV), written first.

## Phase 1: Setup

- [ ] T001 Apply contracts/shared-contract-0.3.2.md to docs/shared-contract.md and ../VFilter/docs/shared-contract.md (identical)
- [ ] T002 [P] EN/AR keys in lang/{en,ar}/panel.php: `lists.*` (page titles, help per list, errors, save notices), `assistants.*`, `cafe.*`
- [ ] T003 [P] Create app/Policies/FilterGaPermissionPolicy.php and FilterListEntryPolicy.php: viewAny/view for any user; create/update/delete use the `manage-filter-settings` gate. Register them.

## Phase 2: Foundational

- [ ] T004 [P] tests/Feature/Lists/ListParserTest.php covers each value format in data-model.md:
  - comment split, blank and comment-only lines, CRLF, trimming;
  - case-insensitive duplicates, with the duplicate count;
  - CIDR normalisation (`1.2.3.4/24` → `1.2.3.0/24`, `2001:DB8::1/64` → `2001:db8::/64`); prefix limits /8–/32 and /32–/128;
  - net cafe refuses CIDR;
  - errors carry line numbers;
  - value and comment limits;
  - the 10,000-entry limit.
- [ ] T005 Create app/Lists/ListName.php (enum with label key, help key, `format()`), app/Lists/IpRange.php (`parse`, `normalise`, `contains`) and app/Lists/ListParser.php with ParsedList

## Phase 3: US1 – Edit lists as lines (P1) 🎯 MVP

- [ ] T006 [P] [US1] tests/Feature/Lists/ListEditorTest.php:
  - `text()` round-trips the stored rows.
  - Save applies only the changes (adds, removes, comment updates, and a case-only change counts as an update), with one audit entry each and the `lists` counter +1.
  - An unchanged save writes nothing.
  - An invalid save writes nothing and throws `InvalidListException`.
  - A stale fingerprint throws `ListChangedException`.
  - A viewer is refused.
- [ ] T007 [US1] Create app/Lists/ListEditor.php (+ SaveResult, InvalidListException, ListChangedException). Order of writes: deletes, then updates, then creates, all inside `ConfigStore::batch`.
- [ ] T008 [P] [US1] tests/Feature/Lists/ListPagesTest.php:
  - Each of the 7 pages renders for every role with its count and help.
  - An admin save with valid text shows a success notice with counts.
  - Invalid text shows line errors on the field.
  - A viewer has a disabled textarea and no save action.
  - Allowed GM IPs: removing the admin's current IP needs confirmation with a warning.
  - A stale fingerprint shows the reload message.
- [ ] T009 [US1] Create app/Filament/Pages/Security/SecurityListPage.php (abstract; form = Textarea `dir=auto`, rows 18; header Save action), 7 subclasses, and resources/views/filament/pages/security-list.blade.php

## Phase 4: US2 – Game Assistants (P1)

- [ ] T010 [P] [US2] tests/Feature/GameAssistants/GameAssistantsTest.php: `add` (validation, case-insensitive unique), `set` (only changes are audited, `ga_permissions` counter), `remove`, and viewer refused.
- [ ] T011 [P] [US2] tests/Feature/GameAssistants/GameAssistantsPageTest.php:
  - Grid columns for all 20 commands; toggling saves.
  - All on / All off / Remove / Add actions.
  - Viewer: toggles disabled, no actions.
- [ ] T012 [US2] Create app/Lists/GameAssistants.php
- [ ] T013 [US2] Create app/Filament/Resources/GameAssistants/ (resource on FilterGaPermission, list page, table with ToggleColumns using `updateStateUsing` → GameAssistants::set)

## Phase 5: US3 – Net cafe limits (P2)

- [ ] T014 [P] [US3] tests/Feature/Lists/CafeLimitsTest.php:
  - `get` defaults to 0.
  - `set` stores the area rule with the exact params; values outside −1..1000 are refused; IPs not in the list are refused.
  - Removing a cafe IP via ListEditor deletes its rule in the same batch.
  - Page: the "Special limits" action fills and saves.
- [ ] T015 [US3] Create app/Lists/CafeLimits.php; hook cafe-rule deletion into ListEditor for `net_cafe_ips`; add the Special limits action to NetCafeIps page

## Phase 6: Polish

- [ ] T016 Add the 8 new routes to tests/Feature/Security/RouteAuthorizationTest.php
- [ ] T017 Run Pint, PHPStan and the full suite; check in the browser (EN/AR, phone); update HANDOFF.md and CLAUDE.md; mark tasks done

## Dependencies
Setup → Foundational → US1 → US3 (needs ListEditor). US2 needs only Setup.
