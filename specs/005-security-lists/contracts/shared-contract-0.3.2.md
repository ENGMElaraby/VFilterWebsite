# Shared contract 0.3.1 → 0.3.2 (PATCH)

Apply to `docs/shared-contract.md` in both repos.

1. Header: `0.3.2 (draft)`.
2. Under `filter_lists`, add:
   > List names: `allowed_ips`, `proxy_bypass_ips`, `proxy_bypass_usernames`, `blocked_ips`, `blocked_hwids`,
   > `net_cafe_ips`, `blocked_words`. `allowed_ips`, `blocked_ips` and `proxy_bypass_ips` hold an IPv4/IPv6 address
   > or a CIDR range in canonical form (`1.2.3.0/24`, `2001:db8::/64`); the filter matches by range.
   > `net_cafe_ips` holds single addresses. Usernames, HWIDs and words compare case-insensitively.
3. Under `filter_area_rules`, add:
   > `cafe_limit`: area `-1`, target = a `net_cafe_ips` address, params
   > `{"login_ip","login_pc","fw_ip","fw_pc","job_ip","job_pc","fgw_ip","fgw_pc"}` as ints (0 = default cafe limit,
   > −1 = unlimited, max 1000).
4. Changelog: `- 0.3.2 (2026-10-17, PATCH): list names and formats (CIDR), cafe_limit rule (feature 005).`
