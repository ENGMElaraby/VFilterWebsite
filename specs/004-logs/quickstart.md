# Quickstart: validate 004 Logs and Live Sessions

```bash
php artisan test --filter='Logs|Sessions|Prune|Export|RouteAuthorization'
php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan
php artisan vfilter:prune-logs          # manual prune run
php artisan schedule:list               # shows vfilter:prune-logs daily 03:30
```

Manual check (`php artisan serve`, using tinker to play the filter):
1. Insert about 200 `filter_logs` rows across levels, categories, IPs and days. Logs shows the last 24 h
   newest first. Each filter narrows the list, and a 7-day range shows older rows.
2. Turn Live on, then insert a row. It appears within 5 s. Go to page 2 and Live turns off.
3. Export opens in Excel with Arabic text intact, and a `=cmd` message shows as text.
4. Set retention to 7 (it asks for confirmation), then run `vfilter:prune-logs`. Rows older than 7 days are
   gone, and the Logs page shows "last prune: N removed".
5. Insert `filter_sessions` rows. Sessions lists them and refreshes. As admin, Kick one and Ban one for 1 day
   with a reason. Two commands appear in the dashboard's recent commands. As viewer, there are no actions.
