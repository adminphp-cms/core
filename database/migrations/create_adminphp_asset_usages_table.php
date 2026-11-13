<?php

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = app(AdminPhp::class)->table('asset_usages');

        Schema::create($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->ulid('id')->primary();
            $blueprint->ulid('asset_id');
            // A module name and the id of its item, or "settings" twice for the settings.
            $blueprint->string('usable_type', 100);
            $blueprint->string('usable_id', 36);

            // Named explicitly: generated names exceed MySQL's 64 character limit.
            $blueprint->unique(['asset_id', 'usable_type', 'usable_id'], "{$table}_unique");
            $blueprint->index(['usable_type', 'usable_id'], "{$table}_usable_index");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('asset_usages'));
    }
};
