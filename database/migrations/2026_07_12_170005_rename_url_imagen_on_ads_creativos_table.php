<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec naming: the creative's URL field applies to any creative type
 * (imagen/video/carrusel), so url_creativo is the accurate name. Raw SQL
 * because Blueprint::renameColumn() needs doctrine/dbal (not installed).
 * 0 rows exist, pure rename.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ads_creativos CHANGE url_imagen url_creativo VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ads_creativos CHANGE url_creativo url_imagen VARCHAR(255) NULL');
    }
};
