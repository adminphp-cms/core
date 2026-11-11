<?php

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(app(AdminPhp::class)->table('revisions'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('revisionable_type', 100);
            $table->ulid('revisionable_id');
            $table->string('status', 20);
            $table->jsonb('payload');
            $table->timestamps();

            $table->index(['revisionable_type', 'revisionable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('revisions'));
    }
};
