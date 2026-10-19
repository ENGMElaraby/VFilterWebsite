# Quickstart: validate 006 Notices

```bash
php artisan test --filter='Notice|TimedBans|KickBan|RouteAuthorization|Translations'
php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan
```

Manual check (`php artisan serve`, as admin, AegisCore running against the same VFILTER DB):
1. Notices: send "  Hello  ". It shows Pending, then Done with "notice sent to N player(s)"; players see "Hello".
2. Send an empty message, a 201-character message, and one with a line break: each is refused.
3. Stop the Agent, send a notice and Cancel it while pending: it shows Cancelled; the audit log has both entries.
4. Sessions → Ban: only Permanent is offered. Set `VFILTER_TIMED_BANS=true`, `php artisan config:clear`: 1 h–30 d return.
5. As viewer: Notices shows the list only.
