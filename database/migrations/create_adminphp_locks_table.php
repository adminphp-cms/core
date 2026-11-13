<?php

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = app(AdminPhp::class)->table('locks');

        Schema::create($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->ulid('id')->primary();
            $blueprint->string('lockable_type', 100);
            $blueprint->ulid('lockable_id');
            $blueprint->ulid('user_id');
            $blueprint->timestamp('locked_at');
            $blueprint->timestamp('renewed_at');

            // Named explicitly: generated names exceed MySQL's 64 character limit.
            $blueprint->unique(['lockable_type', 'lockable_id'], "{$table}_lockable_unique");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('locks'));
    }
};
