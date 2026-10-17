<?php

namespace App\Filter;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;
use Throwable;

/**
 * Read-only reachability check for VFILTER and the optional game databases (spec 003, US5,
 * research R5). SQL Server checks run on a throw-away copy of the connection with a 5 s login
 * and query timeout. Results never include credentials or raw driver messages.
 */
class ConnectionHealthCheck
{
    public const CONNECTIONS = ['filter', 'account', 'shard', 'log'];

    public const TIMEOUT_SECONDS = 5;

    public const CONTRACT_TABLES = [
        'filter_modules', 'filter_settings', 'filter_lists', 'filter_area_rules', 'filter_cooldowns',
        'filter_ga_permissions', 'filter_commands', 'filter_status', 'filter_sessions', 'filter_logs',
        'filter_config_version',
    ];

    private const NANOSECONDS_PER_MILLISECOND = 1_000_000;

    /**
     * @return array<string, HealthResult>
     */
    public function run(): array
    {
        $results = [];

        foreach (self::CONNECTIONS as $name) {
            $results[$name] = $this->check($name);
        }

        return $results;
    }

    private function check(string $name): HealthResult
    {
        $config = config("database.connections.{$name}");

        if (! is_array($config) || blank($config['database'] ?? null)) {
            return new HealthResult(HealthResult::NOT_CONFIGURED);
        }

        $connection = $this->probeConnection($name, $config);
        $started = hrtime(true);

        try {
            DB::connection($connection)->select('SELECT 1');
            $millis = intdiv(hrtime(true) - $started, self::NANOSECONDS_PER_MILLISECOND);

            return $name === 'filter'
                ? new HealthResult(HealthResult::OK, $millis, null, $this->missingTables($connection), count(self::CONTRACT_TABLES))
                : new HealthResult(HealthResult::OK, $millis);
        } catch (Throwable $exception) {
            return new HealthResult(
                HealthResult::UNREACHABLE,
                intdiv(hrtime(true) - $started, self::NANOSECONDS_PER_MILLISECOND),
                $this->reason($exception),
            );
        } finally {
            if ($connection !== $name) {
                DB::purge($connection);
            }
        }
    }

    /**
     * SQL Server: a separate connection with timeouts, so normal traffic keeps its defaults.
     * Other drivers (SQLite in tests and local preview) are checked as they are.
     *
     * @param  array<string, mixed>  $config
     */
    private function probeConnection(string $name, array $config): string
    {
        if (($config['driver'] ?? null) !== 'sqlsrv') {
            return $name;
        }

        $probe = $name.'_health';
        $options = (array) ($config['options'] ?? []);

        if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
            $options[PDO::SQLSRV_ATTR_QUERY_TIMEOUT] = self::TIMEOUT_SECONDS;
        }

        config(["database.connections.{$probe}" => array_merge($config, [
            'login_timeout' => self::TIMEOUT_SECONDS,
            'options' => $options,
        ])]);

        return $probe;
    }

    /**
     * @return list<string>
     */
    private function missingTables(string $connection): array
    {
        $schema = Schema::connection($connection);

        return array_values(array_filter(self::CONTRACT_TABLES, fn (string $table): bool => ! $schema->hasTable($table)));
    }

    private function reason(Throwable $exception): string
    {
        $state = $this->sqlState($exception);
        $message = strtolower($exception->getMessage());

        return match (true) {
            in_array($state, ['HYT00', 'HYT01'], true) || str_contains($message, 'timeout') || str_contains($message, 'timed out') => 'timeout',
            $state === '28000' || str_contains($message, 'login failed') => 'login_failed',
            str_contains($message, 'cannot open database') || str_contains($message, 'does not exist') => 'database_not_found',
            in_array($state, ['08001', '08S01', '08006'], true) || str_contains($message, 'tcp provider') || str_contains($message, 'network') => 'unreachable',
            default => 'error:'.($state ?? 'unknown'),
        };
    }

    private function sqlState(Throwable $exception): ?string
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof PDOException && is_array($current->errorInfo) && isset($current->errorInfo[0])) {
                return (string) $current->errorInfo[0];
            }
        }

        return null;
    }
}
