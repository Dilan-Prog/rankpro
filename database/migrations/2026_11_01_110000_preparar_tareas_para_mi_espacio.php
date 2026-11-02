<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Mi espacio» reutiliza la tabla `tareas` en vez de crear otra: una tarea
 * personal no cuelga de ningún proyecto, así que `proyecto_id` pasa a ser
 * opcional; `user_id` dice de quién es la tarea; `cliente_id` es una etiqueta
 * opcional (la columna se había quitado cuando el cliente se derivaba del
 * proyecto; ahora vuelve porque una tarea personal no tiene proyecto de donde
 * derivarlo). `fecha_inicio` alimenta la línea de tiempo y `orden` el kanban.
 * El estado suma «en_revision».
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sin doctrine/dbal: la nulabilidad se cambia con SQL directo. La FK se conserva.
        DB::statement('ALTER TABLE tareas MODIFY proyecto_id BIGINT UNSIGNED NULL');
        DB::statement("ALTER TABLE tareas MODIFY estado ENUM('pendiente','en_progreso','en_revision','completada') NOT NULL DEFAULT 'pendiente'");

        Schema::table('tareas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('proyecto_id')->constrained('users')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->after('user_id')->constrained('clientes')->nullOnDelete();
            $table->date('fecha_inicio')->nullable()->after('fecha_limite');
            $table->unsignedInteger('orden')->default(0)->after('fecha_inicio');
            $table->index(['user_id', 'estado', 'orden']);
        });
    }

    public function down(): void
    {
        // Las tareas personales (sin proyecto) no caben en el esquema anterior.
        DB::table('tareas')->whereNull('proyecto_id')->delete();
        DB::table('tareas')->where('estado', 'en_revision')->update(['estado' => 'en_progreso']);

        Schema::table('tareas', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'estado', 'orden']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['cliente_id']);
            $table->dropColumn(['user_id', 'cliente_id', 'fecha_inicio', 'orden']);
        });

        DB::statement("ALTER TABLE tareas MODIFY estado ENUM('pendiente','en_progreso','completada') NOT NULL DEFAULT 'pendiente'");
        DB::statement('ALTER TABLE tareas MODIFY proyecto_id BIGINT UNSIGNED NOT NULL');
    }
};
