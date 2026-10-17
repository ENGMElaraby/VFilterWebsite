# Feature Specification: Security Lists

**Feature Branch**: `005-security-lists`

**Created**: 2026-10-17

**Status**: Draft

**Input**: User description: "Security pages backed by filter_lists and filter_ga_permissions: Allowed GM IPs, proxy-bypass IPs/usernames, Game Assistants with a per-command permission grid, Blocked IPs, Blocked HWIDs, Net Cafe IPs with special per-IP limits, blocked words. One value per line with // comments, validated."

## Overview

These are MaxiGuard's Security pages (`docs/maxiguard-feature-map.md`, Security). They are the lists the filter checks on every login and many in-game actions:
- who may log in while the gateway is in GM mode
- who skips proxy/VPN protection
- which accounts are Game Assistants and which GM commands each may use
- which IPs and machines are refused
- which IPs are internet cafes, with their own connection limits
- which words are blocked in chat

Operators edit each list the way MaxiGuard does: one value per line, `//` comments allowed. The filter picks up every change without a restart.

## Clarifications

### Session 2026-10-17

- Q: Should IP lists accept ranges? → A: Allowed GM IPs, Blocked IPs and Proxy-bypass IPs accept a single IPv4/IPv6 address or a CIDR range (e.g. `1.2.3.0/24`). Net Cafe IPs stay single addresses because limits are per IP. The filter must match ranges (contract PATCH).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Edit a list as plain lines (Priority: P1)

An admin opens a list page (Allowed GM IPs, Proxy-bypass IPs, Proxy-bypass usernames, Blocked IPs,
Blocked HWIDs, Net Cafe IPs or Blocked words). They see one text box with one value per line, plus a
`// comment` after a value when one was saved. They paste or edit lines and save. The panel checks
every line, shows which lines are wrong and why, and saves only when all are valid. The filter applies
the new list within seconds.

**Why this priority**: Blocking an attacker's IP or allowing a GM's IP is the most frequent security
task, and it must be fast and hard to get wrong.

**Independent Test**: Paste a mix of valid values, blank lines, duplicates, comments and one invalid
value into Blocked IPs. Saving is refused, and the error names the bad line. Fix it and save. Only the
added and removed values change, each change is audited, and the lists change counter goes up by one.

**Acceptance Scenarios**:

1. **Given** a list with entries, **When** an admin opens its page, **Then** they see every entry on its own line, with its comment after `//`, sorted as saved.
2. **Given** an admin enters `1.2.3.4 // office` and `5.6.7.8`, **When** they save, **Then** two entries exist, the first with comment "office".
3. **Given** a line like `// moved to new office` with no value, **When** saved, **Then** it is ignored (a pure comment line is not an entry).
4. **Given** blank lines and the same value twice, **When** saved, **Then** blank lines are ignored, the duplicate is stored once, and the admin is told how many duplicates were merged.
5. **Given** a line `1.2.3.0/24` in Blocked IPs, **When** saved, **Then** it is stored as a range; the same line in Net Cafe IPs is refused as "single address only".
6. **Given** a line `1.2.3.999`, **When** saved, **Then** nothing is saved and the error says "Line 3: not a valid IP address".
7. **Given** a saved list, **When** an admin removes 2 lines and adds 1, **Then** exactly 2 entries are removed and 1 added, each audited, and the filter is told once that lists changed.
8. **Given** a viewer, **When** they open a list page, **Then** they see the list read-only, with no save button.

---

### User Story 2 - Game Assistants permission grid (Priority: P1)

An admin opens Game Assistants and sees one row per assistant account and one column per GM command
(MakeItem, Zoe, LoadMonster, Mobkill 0, Mobkill 1, Notice, Filter command, Invisible, Invincible, Ban,
RecallUser, RecallGuild, MoveToUser, MoveToNpc, ToTown, GoTown, ResetQ, Wp, GMSkill, WorldStatus).
They add an account, tick the commands it may use, untick others, and remove accounts.

**Why this priority**: Assistants help run events; giving one too many rights (Ban, MakeItem) is a real
risk, so it must be clear and audited.

**Independent Test**: Add assistant `helper1` with Notice and ToTown, then grant Ban, then remove the
account. Each step is saved, audited with old → new, and versioned. A viewer sees the grid but can
change nothing.

**Acceptance Scenarios**:

1. **Given** no assistants, **When** an admin adds username `helper1`, **Then** a row appears with every command off.
2. **Given** a row, **When** the admin ticks Notice and ToTown and saves, **Then** only those two are on, and the audit entry shows exactly those changes.
3. **Given** a row, **When** the admin uses "All on" or "All off" for that row, **Then** every command changes at once and is saved as one change.
4. **Given** an assistant, **When** the admin removes it (with confirmation), **Then** the row and all its permissions are gone and audited.
5. **Given** a username that already is an assistant, or one that is empty or longer than 32 characters, **When** an admin adds it, **Then** it is refused with a message.
6. **Given** a viewer, **When** they open the page, **Then** they see the grid read-only.

---

### User Story 3 - Net cafe IPs with special limits (Priority: P2)

An admin marks IPs as internet cafes in the Net Cafe IPs list (User Story 1). For any cafe IP they can
also set special limits: how many connections (by IP) and how many machines (by PC) are allowed at
Login, Fortress War, Job mode and Fortress Guild War. 0 means "use the normal cafe limit" and −1 means
"unlimited".

**Why this priority**: Cafes are common for this server's players. Without per-cafe limits, normal
IP/PC limits lock out whole cafes, but this is set up rarely.

**Independent Test**: Add cafe IP `5.5.5.5`, set Login IP limit 20, Login PC limit 15 and FW IP −1.
Save, reopen, and check the values. Removing the IP from the cafe list also removes its special limits.

**Acceptance Scenarios**:

1. **Given** a cafe IP, **When** an admin opens its special limits, **Then** they see the 8 limits (Login/FW/Job/FGW × IP/PC), each 0 by default.
2. **Given** an admin enters a limit below −1 or above 1000, **When** they save, **Then** it is refused with a message on that field.
3. **Given** special limits are saved, **When** the IP is removed from the cafe list, **Then** its special limits are removed too, in the same change.
4. **Given** an IP not in the cafe list, **When** an admin tries to set special limits for it, **Then** it is refused.

---

### Edge Cases

- Very long lists (blocked IPs in the thousands): the page handles at least 10,000 lines. A save above 10,000 lines is refused with a message.
- Leading/trailing spaces and Windows line endings are trimmed. A trailing `//` with no text gives an empty comment.
- The comment is limited to 256 characters and the value to the list's limits. Longer lines are refused and named.
- Blocked words compare without letter case (`Hack` and `hack` are one entry). Arabic and Chinese characters are allowed.
- A GM IP the admin is using right now is not removed silently: the confirm message warns when the save removes the admin's own current IP from Allowed GM IPs.
- Two admins edit the same list at once: the second save is refused with "This list changed since you opened it. Reload and apply your changes again."
- The filter-side lists are case-insensitive for usernames and HWIDs. The panel stores them as typed but treats case-only differences as duplicates.

## Requirements *(mandatory)*

### Functional Requirements

**Lists (one value per line)**

- **FR-001**: The system MUST provide one page per list: Allowed GM IPs, Proxy-bypass IPs, Proxy-bypass usernames, Blocked IPs, Blocked HWIDs, Net Cafe IPs and Blocked words. Each page shows the list as editable text, one entry per line, `value // comment`.
- **FR-002**: On save, the system MUST trim lines, ignore blank and comment-only lines, split `value // comment`, merge duplicates (case-insensitive except for IPs, which are normalised), and validate every value. It MUST then report each invalid line by line number and reason, and save nothing if any line is invalid.
- **FR-003**: Validation per list: Allowed GM IPs, Blocked IPs and Proxy-bypass IPs accept an IPv4/IPv6 address or a CIDR range (IPv4 prefix /8–/32, IPv6 /32–/128; host bits are cleared, so `1.2.3.4/24` is stored as `1.2.3.0/24` and the admin is told). Net Cafe IPs accept single addresses only. Usernames take 1–32 characters: letters, digits, `_`, `-`. HWIDs take 1–64 characters with no spaces. Blocked words take 1–64 characters with no line breaks. Comments are at most 256 characters. A list has at most 10,000 entries.
- **FR-004**: A save MUST apply only the difference (adds, removes, comment changes). Each changed entry gets an audit entry, and the filter is told once that lists changed, all in one unit.
- **FR-005**: If the list changed since the page was opened, the save MUST be refused with a reload message (no silent overwrite).
- **FR-006**: Saving Allowed GM IPs in a way that removes the admin's own current IP MUST show a warning and require confirmation.

**Game Assistants**

- **FR-007**: The system MUST show assistants as a grid: rows are usernames, columns are the 20 GM commands, with per-row "All on / All off".
- **FR-008**: Admins MUST be able to add an assistant (username rules as in FR-003, unique, case-insensitive), change its permissions, and remove it with confirmation. Each change is saved with an audit entry (old → new) and a permissions change signal to the filter.

**Net cafe limits**

- **FR-009**: For each IP in Net Cafe IPs, admins MUST be able to set 8 special limits (Login, Fortress War, Job, Fortress Guild War × IP, PC), whole numbers from −1 to 1000 (0 = default cafe limit, −1 = unlimited).
- **FR-010**: Removing an IP from Net Cafe IPs MUST remove its special limits in the same change. Limits can only be set for IPs in the list.

**General**

- **FR-011**: Every list page and the grid MUST be readable by every signed-in role, and editable only by admin and owner (enforced on the server).
- **FR-012**: Each page MUST show its entry count, and explain what the list does and when the filter applies it (live, no restart).
- **FR-013**: All new text MUST be in English and Arabic, and pages MUST work in RTL and at phone width.

### Key Entities

- **List entry**: a value in a named list (allowed GM IPs, proxy-bypass IPs, proxy-bypass usernames, blocked IPs, blocked HWIDs, net cafe IPs, blocked words), with an optional comment.
- **Game Assistant**: a username with 20 yes/no permissions, one per GM command.
- **Cafe special limits**: for one cafe IP, 8 numbers (Login/FW/Job/FGW × IP/PC).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can block a new IP (open page, add line, save) in under 30 seconds.
- **SC-002**: Saving a 10,000-line list completes in under 5 seconds, and re-saving it unchanged changes nothing (no audit, no change signal).
- **SC-003**: 100% of invalid lines are reported with their line number, and an invalid paste never partially saves.
- **SC-004**: Every add, remove and permission change has exactly one audit entry naming the user.
- **SC-005**: No viewer can change any list, assistant or cafe limit, verified for every action.
- **SC-006**: Changes reach the filter's configuration within 2 seconds of saving (one change signal per save).

## Assumptions

- Roles are as in earlier features: admin and owner edit; viewers read.
- Ranges are stored as `address/prefix` in the same value column (fits 45 characters even for IPv6).
- List names in the shared database: `allowed_ips`, `proxy_bypass_ips`, `proxy_bypass_usernames`, `blocked_ips`, `blocked_hwids`, `net_cafe_ips`, `blocked_words`. These go into the shared contract as a PATCH in both repos.
- Cafe special limits are stored as area rules (rule `cafe_limit`, area = everywhere, target = the cafe IP, the 8 limits as details), so no new table is needed. Per-region IP/PC limits (MaxiGuard "Region PC/IP Limits") belong to the area-rules feature (007).
- Global limits (IP, HWID/PC, cafe IP) are settings and belong to the settings feature (006).
- Kicking players who match a newly blocked IP is not automatic. Operators use Sessions (004) to kick.
- Depends on 002 (tables, audited configuration writes) and 001 (roles, audit, EN/AR).
