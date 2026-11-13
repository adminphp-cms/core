<?php

use AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(app(AdminPhp::class)->table('settings'), function (Blueprint $table) {
            $table->string('key', 191)->primary();
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('settings'));
    }
};
