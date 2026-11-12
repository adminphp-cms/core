<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(app(AdminPhp::class)->table('users'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            // Null until an invited user chooses their password.
            $table->string('password')->nullable();
            $table->string('role', 20)->default(UserRole::Editor->value);
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(AdminPhp::class)->table('users'));
    }
};
