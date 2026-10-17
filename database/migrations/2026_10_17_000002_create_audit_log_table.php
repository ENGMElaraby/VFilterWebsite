<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('created_at')->index();
            $table->string('actor_type', 16);
            // No foreign key: entries must outlive the user they mention.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('action', 64);
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();

            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
