<?php

namespace App\Lists;

use App\Config\ConfigStore;
use App\Models\Filter\FilterGaPermission;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Game Assistant accounts and their GM command permissions (spec 005 US2), written through
 * ConfigStore so every change is audited and signals `ga_permissions`.
 */
class GameAssistants
{
    private const USERNAME_MAX_LENGTH = 32;

    private const USERNAME_PATTERN = '/^[A-Za-z0-9_-]+$/';

    public function __construct(private readonly ConfigStore $store) {}

    public function add(string $username): FilterGaPermission
    {
        Gate::authorize('manage-filter-settings');

        $username = trim($username);

        if ($username === '' || mb_strlen($username) > self::USERNAME_MAX_LENGTH || preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            throw new InvalidArgumentException(__('panel.lists.errors.username'));
        }

        if ($this->find($username) !== null) {
            throw new InvalidArgumentException(__('panel.assistants.exists', ['username' => $username]));
        }

        /** @var FilterGaPermission */
        return $this->store->create(FilterGaPermission::class, ['username' => $username, ...array_fill_keys(FilterGaPermission::COMMANDS, false)]);
    }

    /**
     * @param  array<string, bool>  $permissions  command => allowed
     */
    public function set(string $username, array $permissions): void
    {
        Gate::authorize('manage-filter-settings');

        $unknown = array_diff(array_keys($permissions), FilterGaPermission::COMMANDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown GM command: '.implode(', ', $unknown));
        }

        $this->store->update($this->findOrFail($username), array_map(fn (mixed $value): bool => (bool) $value, $permissions));
    }

    public function setAll(string $username, bool $allowed): void
    {
        $this->set($username, array_fill_keys(FilterGaPermission::COMMANDS, $allowed));
    }

    public function remove(string $username): void
    {
        Gate::authorize('manage-filter-settings');

        $this->store->delete($this->findOrFail($username));
    }

    private function find(string $username): ?FilterGaPermission
    {
        return FilterGaPermission::query()->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->first();
    }

    private function findOrFail(string $username): FilterGaPermission
    {
        return $this->find($username) ?? throw new InvalidArgumentException("No assistant {$username}.");
    }
}
