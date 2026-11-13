<?php

use AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = app(AdminPhp::class)->table('related');

        Schema::create($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->ulid('id')->primary();
            $blueprint->string('subject_type', 100);
            $blueprint->ulid('subject_id');
            $blueprint->string('browser_name', 60);
            $blueprint->string('related_type', 100);
            $blueprint->ulid('related_id');
            $blueprint->unsignedInteger('position');

            // Named explicitly: generated names exceed MySQL's 64 character limit.
            $blueprint->index(['subject_type', 'subject_id', 'browser_name', 'position'], "{$table}_subject_index");
            $blueprint->index(['related_type', 'related_id'], "{$table}_related_index");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('related'));
    }
};
