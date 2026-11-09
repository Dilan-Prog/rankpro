<?php

namespace App\Services\Reportes;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Models\Archivo;
use App\Models\Reporte;
use App\Models\ReporteEntrega;
use App\Support\Reportes\NombreEntregable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Genera un entregable de un reporte en un formato, lo deja en el disco local,
 * lo registra como Archivo del cliente y guarda la ReporteEntrega.
 *
 * Regenerar NO borra las entregas anteriores ni las pisa: cada generación
 * escribe su propio fichero, numerado con la versión de esa entrega, para que
 * se pueda saber —y volver a descargar— exactamente qué versión se le envió al
 * cliente y cuándo. Antes todas las generaciones compartían la ruta
 * `{numero}.{formato}` y la última sobrescribía a las demás: el histórico
 * existía en la tabla pero apuntaba N veces al mismo fichero.
 *
 * El trabajo se reparte en tres pasos deliberadamente separados:
 *
 *   1. Transacción corta que reserva la versión bajo `lockForUpdate()` (y completa el folio si falta: ya nace con el borrador).
 *   2. Render (dompdf / PhpSpreadsheet) y escritura del fichero, FUERA de
 *      cualquier transacción: son segundos de CPU y antes se hacían con la
 *      fila del reporte bloqueada, con la transacción abierta todo ese rato.
 *   3. Transacción corta que registra el Archivo y la ReporteEntrega; si falla,
 *      se borra el fichero recién escrito para no dejarlo huérfano en disco.
 */
class PublicadorReporte
{
    /** @param  'pdf'|'xlsx'|'pdf_cliente'  $formato  `pdf_cliente` = versión de 1 página para el cliente, con su propia serie de versiones */
    public function publicar(Reporte $reporte, string $formato): Archivo
    {
        if (! in_array($formato, ['pdf', 'xlsx', 'pdf_cliente'], true)) {
            throw new \InvalidArgumentException("Formato de reporte no soportado: {$formato}.");
        }

        $reporte->loadMissing('cliente');

        // Antes de reservar nada: sin resumen no hay versión para cliente y no debe gastar versión ni cambiar el estado.
        if ($formato === 'pdf_cliente' && app(Armador::class)->armarCliente($reporte)['resumen'] === null) {
            throw new ReporteNoListoException('Este reporte no tiene una sección «Resumen para el cliente» visible. Añádela para generar la versión para cliente.');
        }

        [$emitido, $version, $pasoAListo] = $this->reservar($reporte, $formato);

        // El modelo del llamador se pone al día SOLO con lo que quedó
        // confirmado en la base. Si la reserva revienta, el $reporte que tiene
        // en la mano sigue siendo el de antes: mutarlo primero y confiar en el
        // rollback deja un modelo en memoria que miente (con folio y estado que
        // no existen en la base) y que la vista siguiente pintaría como buenos.
        $reporte->setRawAttributes($emitido->getAttributes(), true);

        // El renderizador se resuelve por formato y no por constructor:
        // generar un PDF no tiene por qué instanciar el motor de xlsx
        // (PhpSpreadsheet) ni al revés.
        try {
            $contenido = match ($formato) {
                'pdf' => app(RenderizadorPdf::class)->generar($reporte),
                'pdf_cliente' => app(RenderizadorPdf::class)->generarCliente($reporte),
                default => app(RenderizadorXlsx::class)->generar($reporte),
            };
        } catch (\Throwable $e) {
            // La reserva ya se commiteó: si el render falla, el reporte no puede quedar «listo»
            // sin ningún entregable. El folio SÍ se conserva (se asignó al crear el borrador).
            if ($pasoAListo) {
                $this->revertirAListo($reporte);
            }

            throw $e;
        }

        $extension = $formato === 'xlsx' ? 'xlsx' : 'pdf';
        $ruta = "clientes/{$reporte->cliente_id}/reportes/{$reporte->numero}".($formato === 'pdf_cliente' ? '-cliente' : '')."-v{$version}.{$extension}";
        Storage::disk('local')->put($ruta, $contenido);

        try {
            return $this->registrar($reporte, $formato, $version, $ruta);
        } catch (\Throwable $e) {
            // Sin esto, un fallo al insertar el Archivo o la entrega deja el
            // fichero en el disco sin ninguna fila que lo referencie: nadie
            // puede descargarlo ni sabe que existe, pero ocupa espacio.
            Storage::disk('local')->delete($ruta);

            throw $e;
        }
    }

    /**
     * Paso 1: reserva folio y versión con la fila del reporte bloqueada.
     *
     * Se trabaja sobre una instancia recién leída y bloqueada, no sobre la que
     * llega por parámetro, para que dos generaciones simultáneas del mismo
     * reporte se serialicen aquí en vez de calcular las dos la misma versión.
     *
     * El tercer elemento dice si ESTA llamada fue la que pasó el reporte de borrador a listo,
     * para poder deshacerlo si el render falla.
     *
     * @return array{0: Reporte, 1: int, 2: bool}
     */
    private function reservar(Reporte $reporte, string $formato): array
    {
        return DB::transaction(function () use ($reporte, $formato) {
            $emitido = Reporte::withTrashed()
                ->whereKey($reporte->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // El folio ya se asigna al crear el borrador (CreadorReporte); aquí solo se completa
            // en reportes anteriores a ese cambio. Las entregas (PDF, XLSX y versión para cliente)
            // de un mismo reporte comparten folio: son el mismo documento en distintos formatos.
            $this->asignarFolio($emitido);

            $pasoAListo = $emitido->estado === EstadoReporte::Borrador;
            if ($pasoAListo) {
                $emitido->estado = EstadoReporte::Listo;
                $emitido->fecha_emision = now();
            }

            $emitido->save();

            // La versión se cuenta por formato: el PDF y el XLSX de un mismo
            // reporte son entregables distintos y cada uno lleva su serie.
            $version = (int) ReporteEntrega::where('reporte_id', $emitido->getKey())
                ->where('formato', $formato)
                ->max('version') + 1;

            return [$emitido, $version, $pasoAListo];
        });
    }

    /**
     * Fija el folio si falta. Bajo bloqueo (`FOR UPDATE` sobre el último folio del año) para
     * que dos altas simultáneas no lean el mismo MAX. El año es el de emisión actual.
     */
    public function asignarFolio(Reporte $reporte): void
    {
        if (filled($reporte->numero)) {
            return;
        }

        $reporte->numero = $this->numeroSiguiente($reporte->area, bloqueando: true);
        $reporte->save();
    }

    /** Deshace el paso a «listo» de un render fallido, solo si no hay ningún entregable registrado. */
    private function revertirAListo(Reporte $reporte): void
    {
        $revertido = Reporte::withTrashed()
            ->whereKey($reporte->getKey())
            ->where('estado', EstadoReporte::Listo->value)
            ->whereDoesntHave('entregas')
            ->update(['estado' => EstadoReporte::Borrador->value, 'fecha_emision' => null]);

        if ($revertido) {
            $reporte->estado = EstadoReporte::Borrador;
            $reporte->fecha_emision = null;
            $reporte->syncOriginal();
        }
    }

    /** Paso 3: el fichero ya existe; queda darle una fila que lo referencie. */
    private function registrar(Reporte $reporte, string $formato, int $version, string $ruta): Archivo
    {
        return DB::transaction(function () use ($reporte, $formato, $version, $ruta) {
            $archivo = Archivo::create([
                'cliente_id' => $reporte->cliente_id,
                // Mismo nombre que la descarga (cliente, periodo, folio y versión): el módulo
                // de Archivos lista varios entregables del mismo reporte y así se distinguen.
                'nombre' => NombreEntregable::para($reporte, $version, $formato),
                'tipo' => 'reporte',
                'ruta_archivo' => $ruta,
                'tamano' => Storage::disk('local')->size($ruta),
                'extension' => $formato === 'xlsx' ? 'xlsx' : 'pdf',
                'subido_por' => Auth::id(),
            ]);

            $reporte->entregas()->create([
                'archivo_id' => $archivo->id,
                'formato' => $formato,
                'version' => $version,
                'generado_por' => Auth::id(),
            ]);

            return $archivo;
        });
    }

    /**
     * Siguiente folio del área para el año en curso: REP-SEO-2026-0001.
     *
     * A diferencia del precedente del proyecto (DocumentosController, que hace
     * `count() + 1`), el consecutivo sale del MAX(numero) ya emitido. El patrón
     * viejo colisiona en cuanto se borra un registro: con 3 folios emitidos y
     * uno borrado, el siguiente count()+1 vuelve a dar 0003, que ya existe, y
     * `reportes.numero` es UNIQUE. Se cuentan también los soft-deleted por la
     * misma razón: su fila sigue ocupando el folio en el índice único.
     *
     * `$bloqueando` añade el FOR UPDATE sobre el último folio del año: sin él,
     * dos generaciones concurrentes leían el mismo MAX, calculaban el mismo
     * consecutivo y la segunda moría con un 500 contra el índice único.
     */
    public function numeroSiguiente(AreaReporte $area, bool $bloqueando = false): string
    {
        $prefijo = $area->prefijo().'-'.now()->format('Y').'-';

        $consulta = Reporte::withTrashed()
            ->where('numero', 'like', $prefijo.'%')
            ->orderByDesc('numero');

        if ($bloqueando) {
            $consulta->lockForUpdate();
        }

        $ultimo = $consulta->value('numero');

        $consecutivo = $ultimo ? (int) substr($ultimo, strlen($prefijo)) : 0;

        return $prefijo.str_pad((string) ($consecutivo + 1), 4, '0', STR_PAD_LEFT);
    }
}
