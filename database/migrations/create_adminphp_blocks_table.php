<?php

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = app(AdminPhp::class)->table('blocks');

        Schema::create($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->ulid('id')->primary();
            $blueprint->string('blockable_type', 100);
            $blueprint->ulid('blockable_id');
            $blueprint->string('editor_name', 60)->default('default');
            $blueprint->foreignUlid('parent_id')->nullable()->constrained($table)->cascadeOnDelete();
            $blueprint->string('child_key', 60)->nullable();
            $blueprint->string('type', 100);
            $blueprint->unsignedSmallInteger('schema_version')->default(1);
            $blueprint->unsignedInteger('position');
            $blueprint->jsonb('content');
            $blueprint->timestamps();

            // Named explicitly: generated names exceed MySQL's 64 character limit.
            $blueprint->index(['blockable_type', 'blockable_id', 'editor_name', 'position'], "{$table}_zone_index");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('blocks'));
    }
};
