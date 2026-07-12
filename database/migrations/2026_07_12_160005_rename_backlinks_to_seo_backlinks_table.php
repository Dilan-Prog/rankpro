<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Renamed for consistent seo_* naming across the module. 0 real rows
 * exist, so this is a pure rename — no column changes needed, the
 * existing schema already matches the spec exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('backlinks', 'seo_backlinks');
    }

    public function down(): void
    {
        Schema::rename('seo_backlinks', 'backlinks');
    }
};
