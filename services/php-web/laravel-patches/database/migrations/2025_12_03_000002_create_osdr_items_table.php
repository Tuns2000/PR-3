<?php
// filepath: services/php-web/laravel-patches/database/migrations/2025_12_03_000002_create_osdr_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('osdr_items', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_id', 100)->unique(); // Уникальный ключ
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->date('release_date')->nullable();
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            
            $table->index('dataset_id');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('osdr_items');
    }
};