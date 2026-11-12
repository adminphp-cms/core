<?php

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(app(AdminPhp::class)->table('password_reset_tokens'), function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('password_reset_tokens'));
    }
};
