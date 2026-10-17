# Quickstart: validate 003 Dashboard and Filter Control

## Prerequisites
- Features 001 and 002 set up (see `HANDOFF.md` Step 0): migrations run, `vfilter:install-defaults`
  done, an owner, an admin and a viewer user exist.
- Local preview without SQL Server: `.env` has `DB_FILTER_DRIVER=sqlite`.

## Automated
```bash
php artisan test --filter='Dashboard|CommandQueue|FilterModule|ConnectionHealth|RouteAuthorization'
php artisan test            # full suite green
./vendor/bin/pint --test && ./vendor/bin/phpstan
```

## Manual walkthrough (`php artisan serve`, http://127.0.0.1:8000/admin)

There is no filter yet, so play the filter by hand with `php artisan tinker` (or SQL).

1. **Status (US1)**: sign in as viewer. All cards show "Offline – never reported" and players show "—".
   In tinker, upsert `filter_status` for `Agent1` with state `running`, online_count 40 and
   heartbeat_at now. Within 5 s Agent1 shows Online with 40 players. Leave it alone for 15 s and it
   flips to Offline.
2. **Commands (US2)**: sign in as admin. Click Restart on Agent1 and confirm. Recent commands shows
   `restart agent1` Pending, plus "Waiting – filter offline" if Agent1 is stale. Click Restart
   again and see the "already in progress" message. Set the row's status to `running`, then `done`
   with result `ok`, and the list follows each change.
3. **Cancel**: queue Stop on gateway, then Cancel it. The status is Cancelled and the audit log has
   `command.queued` and `command.cancelled`. Queue another, set it to `running` in tinker, then try
   Cancel. You get "already started".
4. **All agents**: add Agent2 (Modules page) and enable it. Click All agents → Restart. You get two
   commands, or one plus a "skipped Agent1" message if Agent1 still has one in progress.
5. **Gateway mode (US3)**: choose Public. The modal warns that everyone can log in. A
   `set_gateway_mode {"mode":"public"}` command is queued, and the card shows "changing to Public…"
   until you set `filter_status.gateway_mode = 'public'`.
6. **Modules (US4)**: change Agent1's guard port to 70000 and it is refused. Give Agent2 the same
   guard IP:port as Agent1 and it is refused. A valid change saves, and a "restart needed" notice offers
   Restart now. As viewer, the module addresses show `••••` and there is no edit.
7. **Health (US5)**: open Connection health and run the check. VFILTER shows OK, its time in ms and
   "11/11 tables". With `DB_SHARD_DATABASE=nope` and a bad host, shard shows Unreachable within 5 s
   and no credentials appear. Without it, shard shows Not configured.
8. Switch to Arabic and to phone width: cards and buttons are still usable.
