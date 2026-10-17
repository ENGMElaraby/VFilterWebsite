<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes append-only audit entries. Rules: specs/001-auth-and-shell/contracts/audit-actions.md.
 */
class AuditLogger
{
    private const REDACTED = '[redacted]';

    public function __construct(private readonly ?string $defaultActorType = null) {}

    /**
     * Same logger, with actions attributed to the server console.
     */
    public function asConsole(): self
    {
        return new self('console');
    }

    /**
     * Same logger, with actions attributed to the system (no person involved).
     */
    public function asSystem(): self
    {
        return new self('system');
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        string $action,
        ?Model $target = null,
        array $old = [],
        array $new = [],
        ?string $actorType = null,
        ?string $actorLabel = null,
        ?User $actor = null,
    ): AuditLog {
        $user = $actor ?? Auth::user();
        $resolvedType = $actorType ?? $this->defaultActorType ?? ($user instanceof User ? 'user' : 'system');
        $isUserActor = $resolvedType === 'user' && $user instanceof User;

        return AuditLog::query()->create([
            'created_at' => now('UTC'),
            'actor_type' => $resolvedType,
            'actor_id' => $isUserActor ? $user->getKey() : null,
            'actor_label' => $actorLabel ?? ($isUserActor ? $user->email : null),
            'ip' => $resolvedType === 'console' ? null : request()->ip(),
            'action' => $action,
            'target_type' => $target === null ? null : Str::snake(class_basename($target)),
            'target_id' => $target === null ? null : $this->keyOf($target),
            'old_values' => $old === [] ? null : $this->redact($old),
            'new_values' => $new === [] ? null : $this->redact($new),
        ]);
    }

    /**
     * Run a change and the audit entries it writes in one database transaction.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function transaction(Closure $callback): mixed
    {
        return DB::connection((new AuditLog)->getConnectionName())->transaction($callback);
    }

    private function keyOf(Model $target): string
    {
        $key = $target->getKey();

        return (string) ($key instanceof BackedEnum ? $key->value : $key);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redact(array $values): array
    {
        /** @var list<string> $secretKeys */
        $secretKeys = config('vfilter.audit.redact', []);

        $redacted = [];

        foreach ($values as $key => $value) {
            $redacted[$key] = match (true) {
                in_array($key, $secretKeys, true) => self::REDACTED,
                is_array($value) => $this->redact($value),
                default => $value,
            };
        }

        return $redacted;
    }
}
