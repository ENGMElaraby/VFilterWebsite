# VFilterWebsite — CLAUDE.md

Admin panel for **VFilter**, our own vSRO (Silkroad Online) proxy filter, replacing MaxiGuard.
Admins use it to configure the filter, control it (start/stop, gateway mode), and read its logs.

## Read first
- `HANDOFF.md`: current state, decisions, and what to do next.
- `docs/shared-contract.md`: the database contract with the filter. **Never change it in only one repo.**
- `docs/maxiguard-feature-map.md`: every MaxiGuard panel feature we're matching.
- `.specify/memory/constitution.md`: project rules. They override everything else here.

## References: AegisCore-SRO, go-sro
https://github.com/ENGMElaraby/AegisCore-SRO is our open-source Go server for vSRO 1.188. Check it
for anything about game data or game systems behind a panel page: regions, items, skills, jobs,
party, pets, alchemy, PK, and so on (`_docs/sr-gameserver-*.md`, `_docs/vsro188-*.md`, `db/`), and
for opcode names shown in the logs viewer (`protocol/vsro188/`, `protocol/registry/`). Reference only;
no dependency on it. Its `db/` is its own MySQL schema, not `SRO_VT_SHARD`, so check table names
against our real DB.

Secondary: https://github.com/ENGMElaraby/go-sro, our fork of `ferdoran/go-sro`, the older Go server
AegisCore grew from (last updated 2024-10). Use it only when AegisCore lacks something. If the two
disagree, AegisCore wins.

## Workflow: Spec-Driven Development (GitHub Spec Kit)
Every feature goes through the spec-kit skills in `.claude/skills/`:
1. `/speckit-specify <what and why>` writes `specs/NNN-feature/spec.md`.
2. `/speckit-clarify` resolves open questions *before* planning.
3. `/speckit-plan <tech notes>` writes plan.md, data-model.md, contracts/, quickstart.md.
4. `/speckit-tasks` writes tasks.md.
5. `/speckit-analyze` runs a consistency check across spec, plan and tasks.
6. `/speckit-implement` builds the tasks in order, with tests.

Don't write feature code without a spec and plan in `specs/`. Small fixes and chores are exempt.

## Stack
- **PHP:** latest stable (8.5 at time of writing; check with `php -v`). Use Laravel Herd on macOS.
- **Laravel:** latest stable major, created with `laravel new`. Don't pin an older major.
- **Admin UI:** Filament (latest), on Livewire + Tailwind. The ~50 settings pages are Filament
  pages/resources, generated from a settings catalog, not hand-written forms.
- **Database:** SQL Server via `pdo_sqlsrv`.
  - `filter` connection = `VFILTER` DB (default connection; app tables + filter contract tables)
  - `account`, `shard`, `log` connections = `SRO_VT_ACCOUNT`, `SRO_VT_SHARD`, `SRO_VT_LOG`
    (read-only by default; any write needs its own spec)
- **Tests:** Pest. The feature suite runs on SQLite in-memory for the `filter` connection; SQL
  Server-specific queries get tests tagged `@group sqlsrv`.
- **Localization:** English + Arabic (RTL) from day one. All UI strings go through `__()`.

## Commands
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --database=filter
composer run dev          # serve + queue + vite
php artisan test          # Pest
./vendor/bin/pint         # code style (must pass before commit)
./vendor/bin/phpstan      # static analysis (Larastan, level 6+)
```

## Conventions
- Settings are read and written only through `App\Settings\SettingsRepository`, which bumps
  `filter_config_version` and writes `audit_log` in the same transaction.
- Filter control goes only through `filter_commands`. The website never opens a socket to the filter.
- Status is "online" only if `filter_status.heartbeat_at` is less than 15 s old.
- Never store or show SQL passwords in plain text. They go in `.env`, not in the database.
- Roles: `owner` > `admin` > `viewer`. Every route and Filament page has a policy.
- Times are stored in UTC and shown in the server time zone setting.

## Git
- Remote: `https://github.com/ENGMElaraby/VFilterWebsite`. Branches use spec-kit's feature
  branches (`001-...`); merge to `main` via PR.
