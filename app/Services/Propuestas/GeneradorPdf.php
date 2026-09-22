<?php

namespace App\Services\Propuestas;

use App\Enums\TipoArchivo;
use App\Models\Archivo;
use App\Models\Propuesta;
use App\Support\Propuestas\Calculos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el PDF de una Propuesta de Continuidad, lo deja en el disco local y
 * lo registra como Archivo del cliente.
 *
 * Vive como servicio (y no inline en Admin\PropuestaController::generarPdf)
 * para que el módulo Correo pueda adjuntar exactamente el mismo PDF que
 * descarga el módulo de Propuestas —y la API v1 publicar el mismo entregable—
 * sin duplicar la lógica de folio ni la de registro en Archivos. El
 * controlador solo añade la descarga; la API, la URL firmada.
 *
 * Es el precedente "pequeño" de App\Services\Reportes\PublicadorReporte: no
 * versiona entregas ni bloquea filas. Se movió tal cual estaba en el
 * controlador (refactor puro): mejorar el folio o el registro es otra tarea.
 */
class GeneradorPdf
{
    public function generar(Propuesta $propuesta): Archivo
    {
        // Idempotente: folio/fecha_emision se asignan una sola vez, descargas
        // posteriores mantienen el mismo folio (ver Plan, precedente de Reporte::numero).
        if ($propuesta->folio === null) {
            $numero = 'PROP-CONT-'.now()->format('Y').'-'.str_pad((string) (Propuesta::whereNotNull('folio')->count() + 1), 4, '0', STR_PAD_LEFT);
            $propuesta->update(['folio' => $numero, 'fecha_emision' => now()]);
        }

        $pdf = Pdf::loadView('pdf.propuesta-continuidad', $this->datos($propuesta, $propuesta->folio))
            ->setPaper('letter');

        $path = "clientes/{$propuesta->cliente_id}/propuestas-continuidad/{$propuesta->folio}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        // Quirk heredado del controlador y conservado a propósito: cada
        // generación sobrescribe el mismo fichero pero inserta una fila Archivo
        // nueva, así que descargar dos veces deja dos filas apuntando a la
        // misma ruta. Corregirlo (reutilizar/versionar como PublicadorReporte)
        // cambia comportamiento y queda fuera de este refactor.
        return Archivo::create([
            'cliente_id' => $propuesta->cliente_id,
            'nombre' => "Propuesta de Continuidad {$propuesta->folio} — {$propuesta->cliente->nombre}.pdf",
            'tipo' => TipoArchivo::Propuesta->value,
            'ruta_archivo' => $path,
            'tamano' => Storage::disk('local')->size($path),
            'extension' => 'pdf',
            'subido_por' => Auth::id(),
        ]);
    }

    /**
     * Datos que consume la vista `pdf.propuesta-continuidad`.
     *
     * Todos los cálculos derivados (variación %, subtotal, textos compuestos)
     * se resuelven aquí, nunca dentro del Blade — así se pueden testear aparte.
     * `$folioMostrado` permite imprimir "VISTA PREVIA" sin asignar folio real.
     *
     * @return array<string, mixed>
     */
    public function datos(Propuesta $propuesta, string $folioMostrado): array
    {
        $propuesta->loadMissing('cliente');

        $resumen = $propuesta->resumen ?? [];
        $situacion = $propuesta->situacion_actual ?? [];
        $contexto = $propuesta->contexto_continuidad ?? [];
        $plan = $propuesta->plan_detalle ?? [];
        $condiciones = $propuesta->condiciones_proyeccion ?? [];

        $nombreCliente = $propuesta->cliente->empresa ?: $propuesta->cliente->nombre;
        $partesNombre = explode(' ', trim($nombreCliente), 2);

        $tablaComparacion = collect($situacion['tabla_comparacion'] ?? [])->map(function (array $fila) {
            $variacion = Calculos::variacion($fila['valor_1'] ?? null, $fila['valor_2'] ?? null);

            return $fila + $variacion;
        })->all();

        return [
            'folio' => $folioMostrado,
            'cliente' => $propuesta->cliente,
            'clienteNombre1' => $partesNombre[0] ?? $nombreCliente,
            'clienteNombre2' => $partesNombre[1] ?? '',
            'titulo' => $propuesta->titulo,
            'estado' => $propuesta->estado,
            'fechaEmision' => ($propuesta->fecha_emision ?? now())->translatedFormat('d \d\e F \d\e Y'),
            'precioMensual' => $propuesta->precio_mensual,
            'horasMensuales' => $propuesta->horas_mensuales,
            'tarifaHora' => $propuesta->tarifa_hora,
            'subtotalHoras' => Calculos::subtotal($propuesta->horas_mensuales, $propuesta->tarifa_hora),
            'resumen' => $resumen,
            'situacion' => $situacion,
            'tablaComparacion' => $tablaComparacion,
            'contexto' => $contexto,
            'plan' => $plan,
            'condiciones' => $condiciones,
            // Las plantillas preguntan `$visible('plan.meses')` antes de imprimir
            // cada bloque; la regla (ausente = visible, sección apagada oculta a
            // sus bloques) vive en el modelo, aquí solo se expone.
            'visible' => fn (string $clave) => $propuesta->visible($clave),
        ];
    }
}
