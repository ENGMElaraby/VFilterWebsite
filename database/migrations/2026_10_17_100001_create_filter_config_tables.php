<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration tables the website writes and the filter reads (docs/shared-contract.md 0.2.0).
 */
return new class extends Migration
{
    /** GM commands at the time of this migration (frozen; new ones need a new migration). */
    private const GA_COMMANDS = [
        'make_item', 'zoe', 'load_monster', 'mobkill0', 'mobkill1', 'notice', 'filter_cmd', 'invisible',
        'invincible', 'ban', 'recall_user', 'recall_guild', 'move_to_user', 'move_to_npc', 'to_town',
        'go_town', 'reset_q', 'wp', 'gm_skill', 'world_status',
    ];

    public function up(): void
    {
        Schema::create('filter_modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 16)->index();
            $table->string('name', 32)->unique();
            $table->string('module_ip', 64);
            $table->integer('module_port');
            $table->string('guard_ip', 64);
            $table->integer('guard_port');
            $table->boolean('enabled')->default(false);
        });

        Schema::create('filter_settings', function (Blueprint $table) {
            $table->string('key', 128)->primary();
            $table->text('value');
            $table->string('value_type', 16);
            $table->string('group', 32)->index();
            $table->string('requires_restart', 16)->default('none');
            $table->dateTime('updated_at')->nullable();
            $table->integer('updated_by')->nullable();
        });

        Schema::create('filter_lists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('list', 32);
            $table->string('value', 256);
            $table->string('comment', 256)->nullable();
            $table->unique(['list', 'value']);
        });

        Schema::create('filter_area_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rule', 48);
            $table->string('area', 64)->index();
            $table->string('target', 128)->nullable();
            $table->text('params')->nullable();
            $table->boolean('enabled')->default(true);
            $table->index(['rule', 'enabled']);
        });

        Schema::create('filter_cooldowns', function (Blueprint $table) {
            $table->string('action', 32)->primary();
            $table->integer('seconds')->default(0);
        });

        Schema::create('filter_ga_permissions', function (Blueprint $table) {
            $table->string('username', 32)->primary();

            foreach (self::GA_COMMANDS as $command) {
                $table->boolean($command)->default(false);
            }
        });

        Schema::create('filter_config_version', function (Blueprint $table) {
            $table->string('area', 16)->primary();
            $table->bigInteger('version')->default(0);
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filter_config_version');
        Schema::dropIfExists('filter_ga_permissions');
        Schema::dropIfExists('filter_cooldowns');
        Schema::dropIfExists('filter_area_rules');
        Schema::dropIfExists('filter_lists');
        Schema::dropIfExists('filter_settings');
        Schema::dropIfExists('filter_modules');
    }
};
