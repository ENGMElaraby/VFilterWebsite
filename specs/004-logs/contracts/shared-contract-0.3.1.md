# Shared contract 0.3.0 → 0.3.1 (PATCH)

Apply the same edit to `docs/shared-contract.md` in both repos.

1. Header: `**Version:** 0.3.1 (draft)`.
2. Under the filter_commands "Claiming and cancelling" note, add:
   > **Kick and ban payloads.** `kick`: target = charname, else username, else IP;
   > payload `{"session_id":123,"ip":"1.2.3.4","reason":null}`. `ban`: target = username (game account);
   > payload `{"session_id":123,"reason":"...","duration_minutes":1440}` (`null` = permanent). The filter
   > bans the account and disconnects it. The website queues at most one pending/running kick/ban per target.
3. Changelog: `- 0.3.1 (2026-10-17, PATCH): kick/ban payload shapes (feature 004).`
