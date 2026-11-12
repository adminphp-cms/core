<?php

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = app(AdminPhp::class)->table('assets');

        Schema::create($tableName, function (Blueprint $table) use ($tableName) {
            $table->ulid('id')->primary();
            $table->string('disk', 50);
            $table->string('path');
            $table->string('name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->ulid('user_id')->nullable()->index();
            $table->timestamps();

            $table->index('created_at', "{$tableName}_created_at_index");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('assets'));
    }
};
