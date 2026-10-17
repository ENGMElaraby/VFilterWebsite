# Data Model: Security Lists (005)

No new tables or migrations.

## ListName (enum `App\Lists\ListName`, values are `filter_lists.list`)
| value | page | value format | max |
| --- | --- | --- | --- |
| `allowed_ips` | Allowed GM IPs | IP or CIDR | 45 |
| `proxy_bypass_ips` | Proxy-bypass IPs | IP or CIDR | 45 |
| `proxy_bypass_usernames` | Proxy-bypass usernames | `[A-Za-z0-9_-]{1,32}`, case-insensitive | 32 |
| `blocked_ips` | Blocked IPs | IP or CIDR | 45 |
| `blocked_hwids` | Blocked HWIDs | `\S{1,64}`, case-insensitive | 64 |
| `net_cafe_ips` | Net Cafe IPs | single IP | 45 |
| `blocked_words` | Blocked words | 1–64 chars, no line breaks, case-insensitive | 64 |

Comment: ≤ 256 characters, null when empty. A list holds at most 10,000 entries.

## ParsedList
`entries: array<key, {value, comment, line}>`, `errors: list<{line, reason}>`, `duplicates: int`,
`normalised: list<{line, from, to}>`.

## SaveResult
`added`, `removed`, `updated` (comment changes); all 0 means nothing was written.

## Cafe special limits (`filter_area_rules`)
`rule='cafe_limit'`, `area='-1'`, `target=<cafe ip>`, `enabled=1`,
`params={login_ip, login_pc, fw_ip, fw_pc, job_ip, job_pc, fgw_ip, fgw_pc}` (ints −1..1000; 0 = default, −1 = unlimited).

## Game Assistant (`filter_ga_permissions`)
`username` (PK, 1–32 `[A-Za-z0-9_-]`, unique case-insensitive) plus the 20 command booleans (002).

## Audit actions
Existing ConfigStore actions: `lists.created|updated|deleted`, `ga_permissions.created|updated|deleted`,
`area_rules.created|updated|deleted`.
