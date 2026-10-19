# Contract: Notices services (006)

- `CommandQueue::NOTICE_MAX_LENGTH = 200`.
- `CommandQueue::noticeError(string $message): ?string` is pure: the translated reason the trimmed message is
  invalid (`required`, `too_long`, `one_line`), or null. Used by the form and by `notice()`.
- `CommandQueue::notice(string $message): FilterCommand` authorises `create` FilterCommand, throws
  `InvalidArgumentException` (message = `noticeError()`), `NoticeAlreadyQueuedException` (same message pending or
  running) or `AuthorizationException`; queues `notice` / `all` / `{"message"}` with a `command.queued` audit entry.
- `CommandQueue::banDurations(): list<int>`: `BAN_DURATIONS` when `vfilter.timed_bans` is true, else `[]`.
  `ban()` throws `InvalidArgumentException` for a duration not in it.

Page: `/admin/notices` (`filament.admin.pages.notices`). Every role can open it; admin and owner send and cancel.
