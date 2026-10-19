<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * filter_status.details: JSON written by the filter (e.g. GameServer hook status). docs/shared-contract.md 0.4.0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filter_status', function (Blueprint $table) {
            $table->text('details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('filter_status', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
