<?php

namespace App\Lists;

use App\Config\ConfigStore;
use App\Models\Filter\FilterAreaRule;
use App\Models\Filter\FilterListEntry;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Special connection limits for one net cafe IP, stored as a `cafe_limit` area rule
 * (shared contract 0.3.2, spec 005 US3).
 */
class CafeLimits
{
    public const RULE = 'cafe_limit';

    public const AREA_EVERYWHERE = '-1';

    public const FIELDS = ['login_ip', 'login_pc', 'fw_ip', 'fw_pc', 'job_ip', 'job_pc', 'fgw_ip', 'fgw_pc'];

    public const UNLIMITED = -1;

    public const MAX = 1000;

    public function __construct(private readonly ConfigStore $store) {}

    /**
     * @return array<string, int> every field, 0 when not set
     */
    public function get(string $ip): array
    {
        $params = $this->rule($ip)->params ?? [];

        return array_combine(self::FIELDS, array_map(fn (string $field): int => (int) ($params[$field] ?? 0), self::FIELDS));
    }

    /**
     * @return list<string>
     */
    public function cafeIps(): array
    {
        return FilterListEntry::query()->where('list', ListName::NetCafeIps->value)->orderBy('value')->pluck('value')->all();
    }

    /**
     * @param  array<string, mixed>  $limits
     */
    public function set(string $ip, array $limits): void
    {
        Gate::authorize('manage-filter-settings');

        $params = [];

        foreach (self::FIELDS as $field) {
            $value = (int) ($limits[$field] ?? 0);

            if ($value < self::UNLIMITED || $value > self::MAX) {
                throw new InvalidArgumentException("{$field} must be between ".self::UNLIMITED.' and '.self::MAX.'.');
            }

            $params[$field] = $value;
        }

        // Membership check and write in one transaction, with the list row locked, so a cafe IP
        // removed meanwhile cannot leave an orphan rule (FR-010).
        $this->store->batch(function (ConfigStore $store) use ($ip, $params): void {
            $isCafe = FilterListEntry::query()->where('list', ListName::NetCafeIps->value)->where('value', $ip)->lockForUpdate()->exists();

            if (! $isCafe) {
                throw new InvalidArgumentException(__('panel.cafe.not_cafe', ['ip' => $ip]));
            }

            $rule = $this->rule($ip);

            if ($rule === null) {
                $store->create(FilterAreaRule::class, [
                    'rule' => self::RULE, 'area' => self::AREA_EVERYWHERE, 'target' => $ip, 'params' => $params, 'enabled' => true,
                ]);

                return;
            }

            $store->update($rule, ['params' => $params]);
        });
    }

    private function rule(string $ip): ?FilterAreaRule
    {
        return FilterAreaRule::query()->where('rule', self::RULE)->where('target', $ip)->first();
    }
}
