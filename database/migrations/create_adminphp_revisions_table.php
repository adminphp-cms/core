<?php

use AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = app(AdminPhp::class)->table('revisions');

        Schema::create($tableName, function (Blueprint $table) use ($tableName) {
            $table->ulid('id')->primary();
            $table->string('revisionable_type', 100);
            $table->ulid('revisionable_id');
            $table->string('status', 20);
            $table->jsonb('payload');
            $table->ulid('user_id')->nullable()->index();
            $table->timestamps();

            // Named explicitly: generated names exceed MySQL's 64 character limit.
            $table->index(['revisionable_type', 'revisionable_id', 'status'], "{$tableName}_revisionable_index");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('revisions'));
    }
};
