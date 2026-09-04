<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de posiciones del banco de keywords.
 *
 * Hasta ahora `keywords` sólo guardaba `posicion_actual` y `posicion_anterior`:
 * una ventana de dos valores que perdía el mes anterior en cuanto se volvía a
 * medir, así que no había forma de enseñarle al cliente la evolución.
 *
 * Cada fila es la medición de una keyword en una fecha. Una "ronda" —lo que el
 * equipo captura de una sentada para toda una lista— es simplemente el conjunto
 * de mediciones que comparten `lista_id` y `fecha`; no hace falta una tabla
 * aparte para agruparlas, y así una keyword que cambia de lista conserva su
 * historia.
 *
 * `lista_id` se guarda aunque se pueda deducir de la keyword: deja constancia de
 * en qué lista se tomó la medición, que es lo que agrupa la ronda. Ojo: la
 * matriz del histórico se construye a partir de las keywords que la lista tiene
 * AHORA, no de esta columna. Es deliberado: la lista es un conjunto de trabajo
 * vivo, y enseñar filas de keywords que ya no están en ella confundiría más de
 * lo que aporta. La columna sirve para saber de dónde salió cada medición y
 * para el índice por ronda, no como criterio de consulta.
 *
 * `nota` es el motivo de todo esto: qué se hizo ese mes para mover la posición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_mediciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('keywords')->onDelete('cascade');
            $table->foreignId('lista_id')->nullable()->constrained('keyword_listas')->nullOnDelete();
            $table->date('fecha');
            // Nullable a propósito: "no aparece en el top 100" es un dato, y no
            // es lo mismo que no haberla medido. La fila existe, la posición no.
            $table->unsignedInteger('posicion')->nullable();
            $table->string('url')->nullable();
            $table->text('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Una keyword no puede tener dos posiciones distintas el mismo día:
            // volver a medir corrige la medición, no añade una segunda.
            $table->unique(['keyword_id', 'fecha']);
            $table->index(['lista_id', 'fecha']);
        });

        // Semilla con lo que ya hay: cada keyword con posición conocida entra al
        // histórico con la fecha de su última edición. Sin esto, el módulo
        // arrancaría vacío y se perderían las posiciones ya capturadas, que son
        // el punto de partida contra el que se mide el avance.
        DB::table('keywords')
            ->whereNotNull('posicion_actual')
            ->orderBy('id')
            ->chunkById(200, function ($keywords) {
                $filas = [];

                foreach ($keywords as $k) {
                    $filas[] = [
                        'keyword_id' => $k->id,
                        'lista_id' => $k->lista_id,
                        'fecha' => Carbon::parse($k->updated_at ?? now())->toDateString(),
                        // El banco admitía 0 como posición, que en un SERP no
                        // existe: era el marcador de "sin dato". En el histórico
                        // eso es null, que es lo que significa de verdad.
                        'posicion' => $k->posicion_actual ?: null,
                        'url' => $k->url_asignada,
                        'nota' => null,
                        'registrado_por' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('keyword_mediciones')->insert($filas);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_mediciones');
    }
};
