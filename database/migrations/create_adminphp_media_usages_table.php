<?php

use AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = app(AdminPhp::class)->table('media_usages');
        $formerTable = app(AdminPhp::class)->table('asset_usages');

        // Installed before the media library was renamed: keep the usages, rename the table.
        if (Schema::hasTable($formerTable) && ! Schema::hasTable($table)) {
            Schema::rename($formerTable, $table);

            Schema::table($table, function (Blueprint $blueprint) use ($formerTable, $table) {
                $blueprint->renameColumn('asset_id', 'media_id');
                $blueprint->renameIndex("{$formerTable}_unique", "{$table}_unique");
                $blueprint->renameIndex("{$formerTable}_usable_index", "{$table}_usable_index");
            });

            return;
        }

        Schema::create($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->ulid('id')->primary();
            $blueprint->ulid('media_id');
            // A module name and the id of its item, or "settings" twice for the settings.
            $blueprint->string('usable_type', 100);
            $blueprint->string('usable_id', 36);

            // Named explicitly: generated names exceed MySQL's 64 character limit.
            $blueprint->unique(['media_id', 'usable_type', 'usable_id'], "{$table}_unique");
            $blueprint->index(['usable_type', 'usable_id'], "{$table}_usable_index");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('media_usages'));
    }
};
