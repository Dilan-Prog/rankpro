<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los tres son opcionales y sin default: los clientes existentes quedan igual. `sitio_web` es la fuente de la
     * que se PRECARGAN los demás formularios (SEO, Search Console, reportes); no se migran ni se unifican datos viejos.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('giro', 40)->nullable()->after('empresa');
            $table->string('sitio_web', 255)->nullable()->after('giro');
            $table->string('ciudad', 120)->nullable()->after('sitio_web');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['giro', 'sitio_web', 'ciudad']);
        });
    }
};
