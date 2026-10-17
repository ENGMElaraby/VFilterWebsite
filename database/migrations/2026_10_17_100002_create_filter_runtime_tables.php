<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime tables: the command queue (website writes, filter processes) and the tables the filter
 * writes (status heartbeat, live sessions, logs). docs/shared-contract.md 0.2.0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filter_commands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('command', 32);
            $table->string('target', 64);
            $table->text('payload')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('result')->nullable();
            $table->integer('created_by');
            $table->dateTime('created_at');
            $table->dateTime('processed_at')->nullable();
            $table->index(['status', 'created_at']);
        });

        Schema::create('filter_status', function (Blueprint $table) {
            $table->string('module', 32)->primary();
            $table->string('state', 16)->default('stopped');
            $table->integer('online_count')->default(0);
            $table->integer('connections')->default(0);
            $table->string('gateway_mode', 16)->nullable();
            $table->string('version', 32)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('heartbeat_at')->nullable();
            $table->text('last_error')->nullable();
        });

        Schema::create('filter_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('module', 32)->index();
            $table->string('ip', 45)->index();
            $table->string('hwid', 64)->nullable();
            $table->string('username', 32)->nullable()->index();
            $table->string('charname', 64)->nullable();
            $table->dateTime('connected_at');
            $table->dateTime('last_packet_at')->nullable();
        });

        Schema::create('filter_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('ts')->index();
            $table->string('level', 8);
            $table->string('module', 32);
            $table->string('category', 32);
            $table->string('ip', 45)->nullable()->index();
            $table->string('hwid', 64)->nullable();
            $table->string('username', 32)->nullable()->index();
            $table->string('charname', 64)->nullable();
            $table->string('opcode', 8)->nullable();
            $table->string('message', 1024);
            $table->text('data')->nullable();
            $table->index(['category', 'ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filter_logs');
        Schema::dropIfExists('filter_sessions');
        Schema::dropIfExists('filter_status');
        Schema::dropIfExists('filter_commands');
    }
};
