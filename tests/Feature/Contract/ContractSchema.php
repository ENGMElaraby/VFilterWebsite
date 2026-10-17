<?php

namespace Tests\Feature\Contract;

/**
 * Expected VFILTER contract structure (specs/002-contract-schema/data-model.md).
 * columns: name => [sql type on SQL Server, nullable]
 */
final class ContractSchema
{
    public const GA_COMMANDS = [
        'make_item', 'zoe', 'load_monster', 'mobkill0', 'mobkill1', 'notice', 'filter_cmd', 'invisible',
        'invincible', 'ban', 'recall_user', 'recall_guild', 'move_to_user', 'move_to_npc', 'to_town',
        'go_town', 'reset_q', 'wp', 'gm_skill', 'world_status',
    ];

    /**
     * @return array<string, array{columns: array<string, array{0: string, 1: bool}>, primary: list<string>, indexes: list<array{0: list<string>, 1: bool}>}>
     */
    public static function tables(): array
    {
        return [
            'filter_modules' => [
                'columns' => [
                    'id' => ['int', false], 'type' => ['nvarchar(16)', false], 'name' => ['nvarchar(32)', false],
                    'module_ip' => ['nvarchar(64)', false], 'module_port' => ['int', false],
                    'guard_ip' => ['nvarchar(64)', false], 'guard_port' => ['int', false], 'enabled' => ['bit', false],
                ],
                'primary' => ['id'],
                'indexes' => [[['type'], false], [['name'], true]],
            ],
            'filter_settings' => [
                'columns' => [
                    'key' => ['nvarchar(128)', false], 'value' => ['nvarchar(max)', false],
                    'value_type' => ['nvarchar(16)', false], 'group' => ['nvarchar(32)', false],
                    'requires_restart' => ['nvarchar(16)', false], 'updated_at' => ['datetime2', true],
                    'updated_by' => ['int', true],
                ],
                'primary' => ['key'],
                'indexes' => [[['group'], false]],
            ],
            'filter_lists' => [
                'columns' => [
                    'id' => ['bigint', false], 'list' => ['nvarchar(32)', false],
                    'value' => ['nvarchar(256)', false], 'comment' => ['nvarchar(256)', true],
                ],
                'primary' => ['id'],
                'indexes' => [[['list', 'value'], true]],
            ],
            'filter_area_rules' => [
                'columns' => [
                    'id' => ['bigint', false], 'rule' => ['nvarchar(48)', false], 'area' => ['nvarchar(64)', false],
                    'target' => ['nvarchar(128)', true], 'params' => ['nvarchar(max)', true], 'enabled' => ['bit', false],
                ],
                'primary' => ['id'],
                'indexes' => [[['area'], false], [['rule', 'enabled'], false]],
            ],
            'filter_cooldowns' => [
                'columns' => ['action' => ['nvarchar(32)', false], 'seconds' => ['int', false]],
                'primary' => ['action'],
                'indexes' => [],
            ],
            'filter_ga_permissions' => [
                'columns' => ['username' => ['nvarchar(32)', false]]
                    + array_fill_keys(self::GA_COMMANDS, ['bit', false]),
                'primary' => ['username'],
                'indexes' => [],
            ],
            'filter_config_version' => [
                'columns' => [
                    'area' => ['nvarchar(16)', false], 'version' => ['bigint', false], 'updated_at' => ['datetime2', true],
                ],
                'primary' => ['area'],
                'indexes' => [],
            ],
            'filter_commands' => [
                'columns' => [
                    'id' => ['bigint', false], 'command' => ['nvarchar(32)', false], 'target' => ['nvarchar(64)', false],
                    'payload' => ['nvarchar(max)', true], 'status' => ['nvarchar(16)', false],
                    'result' => ['nvarchar(max)', true], 'created_by' => ['int', false],
                    'created_at' => ['datetime2', false], 'processed_at' => ['datetime2', true],
                ],
                'primary' => ['id'],
                'indexes' => [[['status', 'created_at'], false]],
            ],
            'filter_status' => [
                'columns' => [
                    'module' => ['nvarchar(32)', false], 'state' => ['nvarchar(16)', false],
                    'online_count' => ['int', false], 'connections' => ['int', false],
                    'gateway_mode' => ['nvarchar(16)', true], 'version' => ['nvarchar(32)', true],
                    'started_at' => ['datetime2', true], 'heartbeat_at' => ['datetime2', true],
                    'last_error' => ['nvarchar(max)', true],
                ],
                'primary' => ['module'],
                'indexes' => [],
            ],
            'filter_sessions' => [
                'columns' => [
                    'id' => ['bigint', false], 'module' => ['nvarchar(32)', false], 'ip' => ['nvarchar(45)', false],
                    'hwid' => ['nvarchar(64)', true], 'username' => ['nvarchar(32)', true],
                    'charname' => ['nvarchar(64)', true], 'connected_at' => ['datetime2', false],
                    'last_packet_at' => ['datetime2', true],
                ],
                'primary' => ['id'],
                'indexes' => [[['ip'], false], [['module'], false], [['username'], false]],
            ],
            'filter_logs' => [
                'columns' => [
                    'id' => ['bigint', false], 'ts' => ['datetime2', false], 'level' => ['nvarchar(8)', false],
                    'module' => ['nvarchar(32)', false], 'category' => ['nvarchar(32)', false],
                    'ip' => ['nvarchar(45)', true], 'hwid' => ['nvarchar(64)', true],
                    'username' => ['nvarchar(32)', true], 'charname' => ['nvarchar(64)', true],
                    'opcode' => ['nvarchar(8)', true], 'message' => ['nvarchar(1024)', false],
                    'data' => ['nvarchar(max)', true],
                ],
                'primary' => ['id'],
                'indexes' => [[['category', 'ts'], false], [['ip'], false], [['ts'], false], [['username'], false]],
            ],
        ];
    }
}
