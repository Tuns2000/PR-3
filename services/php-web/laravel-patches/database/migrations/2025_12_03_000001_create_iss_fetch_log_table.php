<?php
// filepath: services/php-web/laravel-patches/database/migrations/2025_12_03_000001_create_iss_fetch_log_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iss_fetch_log', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->decimal('altitude', 10, 2);
            $table->decimal('velocity', 10, 2);
            $table->timestampTz('timestamp')->unique(); // Уникальный ключ
            $table->timestampTz('fetched_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            
            $table->index('timestamp');
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iss_fetch_log');
    }
};