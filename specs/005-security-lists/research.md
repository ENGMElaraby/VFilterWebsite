# Research: Security Lists (005)

## R1. Text editing and saving only what changed
- **Decision**: `ListParser` turns the textarea into normalised entries. It trims lines, drops blank and `//`-only
  lines, splits at the first ` //` or `//`, normalises the value and keys it case-insensitively. It returns entries
  plus line-numbered errors. `ListEditor::save()` compares the entries with the DB rows by key and calls
  `ConfigStore::batch()` for the creates, deletes and comment updates. Each change gets one audit entry, and the
  `lists` counter is bumped once per batch (002 behaviour). An unchanged save writes nothing.
- **Concurrent edits**: the page keeps a fingerprint (sha1 of the sorted `value|comment` rows) taken at load.
  The save recomputes it inside the transaction and throws `ListChangedException` if it differs.
- **Alternatives**: a row-per-entry table UI (slower for pasting thousands of IPs, rejected); delete-all-and-reinsert
  (noisy audit, bumps the counter for no-ops, rejected).

## R2. IP and CIDR validation
- `filter_var(FILTER_VALIDATE_IP)` for addresses. For CIDR, the prefix is /8–/32 for IPv4 and /32–/128 for IPv6.
  Host bits are cleared with `inet_pton` masking and the stored form is canonical (`inet_ntop`), so
  `2001:DB8::1/64` becomes `2001:db8::/64`. The UI reports the normalisation as a notice, not an error.
- The admin's own-IP check uses the same mask code (`IpRange::contains`).

## R3. Cafe special limits without a new table
- They are stored in `filter_area_rules`: `rule = 'cafe_limit'`, `area = '-1'`, `target = <ip>`, and
  `params = {"login_ip":0,"login_pc":0,"fw_ip":0,"fw_pc":0,"job_ip":0,"job_pc":0,"fgw_ip":0,"fgw_pc":0}`.
  The values are −1..1000. Removing a cafe IP deletes its rule in the same batch, so both the `lists` and
  `area_rules` counters move once.

## R4. Game Assistants grid
- A Filament resource on `FilterGaPermission` (string key `username`) with 20 `ToggleColumn`s. Each toggle saves
  through `GameAssistants::set($username, [$command => bool])` via `ConfigStore::update` (audited old → new,
  `ga_permissions` counter). Row actions: All on, All off, Remove. Header action: Add. Toggles are disabled for
  viewers, and the service authorises again.

## R5. Pages
- An abstract `SecurityListPage` (Filament Page) with a textarea form and a Save action that confirms
  conditionally (own-IP removal for Allowed GM IPs). Seven small subclasses set their `ListName`, slug, icon and sort.
  `NetCafeIps` adds a "Special limits" header action (IP select + 8 numeric fields). Everything sits in nav group "Security".
- Authorisation: the `manage-filter-settings` gate (004) for every write. Reads are open to every role.

## R6. Contract 0.3.2 (PATCH, both repos)
- It lists the 7 list names and their value formats (CIDR allowed in `allowed_ips`, `blocked_ips`,
  `proxy_bypass_ips`), notes that usernames, HWIDs and words compare case-insensitively, and documents the
  `cafe_limit` area rule.
