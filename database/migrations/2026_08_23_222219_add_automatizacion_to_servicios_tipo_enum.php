<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE servicios MODIFY COLUMN tipo ENUM('seo', 'google_ads', 'meta_ads', 'tiktok_ads', 'rediseno', 'software', 'automatizacion') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE servicios MODIFY COLUMN tipo ENUM('seo', 'google_ads', 'meta_ads', 'tiktok_ads', 'rediseno', 'software') NOT NULL");
    }
};
