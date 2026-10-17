# Quickstart: validate 005 Security Lists

```bash
php artisan test --filter='Lists|GameAssistant|CafeLimit|RouteAuthorization'
php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan
```

Manual check (`php artisan serve`, as admin):
1. Blocked IPs: paste `1.2.3.4 // test`, `10.0.0.5/24`, `bad`, then save. Line 3 is reported. Remove it and save.
   The `/24` is normalised to `10.0.0.0/24`. The audit log shows 2 `lists.created`, and the `lists` counter goes up by 1.
2. Save again unchanged: nothing is written.
3. Net Cafe IPs: add `5.5.5.5` and set Special limits Login IP 20 and FW IP −1. Remove the IP and the limits disappear.
4. Game Assistants: add `helper1`, toggle Notice, use All on, then Remove.
5. As viewer: every page is read-only.
