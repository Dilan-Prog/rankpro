<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `html_personalizado` era TEXT (tope real ~65,535 caracteres en MySQL),
 * mientras la validación del controlador permitía hasta 200,000: un HTML
 * pegado con imágenes en base64 (fácil pasar de 65k) se habría truncado en
 * silencio al guardar, sin ningún error visible. LONGTEXT soporta hasta ~4GB,
 * de sobra para cualquier plantilla de correo con imágenes incrustadas.
 */
return new class extends Migration
{
    // SQL crudo en vez de Schema::table(...)->change() para no depender de
    // doctrine/dbal (no instalado en el proyecto) solo por este cambio.
    public function up(): void
    {
        DB::statement('ALTER TABLE correo_plantillas MODIFY html_personalizado LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE correo_plantillas MODIFY html_personalizado TEXT NULL');
    }
};
