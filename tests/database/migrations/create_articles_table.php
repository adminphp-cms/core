<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->jsonb('title');
            $table->jsonb('lead')->nullable();
            $table->jsonb('slug')->nullable();
            $table->string('category', 50)->nullable();
            $table->jsonb('tags')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->date('event_on')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->publication();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
