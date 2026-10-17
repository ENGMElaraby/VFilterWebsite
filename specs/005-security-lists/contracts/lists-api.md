# Contract: Security lists services (005)

- `ListParser::parse(ListName $list, string $text): ParsedList` is pure.
- `ListEditor::text(ListName): string`, `fingerprint(ListName): string`,
  `save(ListName, string $text, string $fingerprint): SaveResult`. It throws `InvalidListException` (carries
  `ParsedList` errors), `ListChangedException`, or `AuthorizationException`. It authorises with `manage-filter-settings`.
- `CafeLimits::get(string $ip): array<string,int>` and `set(string $ip, array $limits): void`. `set` refuses
  IPs not in `net_cafe_ips` and values outside −1..1000.
- `GameAssistants::add(string $username)`, `set(string $username, array<string,bool>)`, `remove(string $username)`.

Pages: `/admin/security/{allowed-ips|proxy-bypass-ips|proxy-bypass-usernames|blocked-ips|blocked-hwids|net-cafe-ips|blocked-words}`
and `/admin/game-assistants`. Every role can read; admin and owner can write.
