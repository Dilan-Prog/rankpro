<?php

namespace Tests\Feature\Portal;

use App\Enums\AreaReporte;
use App\Enums\EstadoPropuesta;
use App\Enums\EstadoReporte;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\MetricaCliente;
use App\Models\MetricaClienteValor;
use App\Models\PortalAcceso;
use App\Models\Propuesta;
use App\Models\Reporte;
use App\Models\ReporteEntrega;
use App\Support\Propuestas\Plantilla;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/** Datos comunes de las pruebas del portal. Marcadores MAYÚSCULAS para buscarlos en el HTML y los PDF. */
trait PreparaPortal
{
    private int $folioPortal = 0;

    protected function prepararPortal(): void
    {
        $this->withoutVite();
        // El límite de 60 peticiones/min por IP usa la caché y, con la hora congelada, se acumula entre
        // tests del mismo proceso: sin esto un test tardío recibe 429 en vez de lo que prueba.
        Cache::flush();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 11, 11, 10, 0, 0));
    }

    protected function cerrarPortal(): void
    {
        Carbon::setTestNow();
    }

    protected function cliente(string $empresa = 'Hotel Fratelli', array $extra = []): Cliente
    {
        return Cliente::factory()->create($extra + ['empresa' => $empresa]);
    }

    /** @return array{0: PortalAcceso, 1: string} */
    protected function enlace(Cliente $cliente, int $dias = 30): array
    {
        $nuevo = PortalAcceso::crearPara($cliente, null, $dias);

        return [$nuevo['acceso'], $nuevo['token']];
    }

    protected function ruta(string $nombre, string $token, array $extra = []): string
    {
        return route($nombre, ['token' => $token] + $extra);
    }

    /**
     * Reporte con su resumen para cliente y una sección INTERNA, más una entrega por formato.
     *
     * @param  list<string>  $formatos
     */
    protected function reporte(Cliente $cliente, string $estado = 'entregado', array $formatos = ['pdf_cliente', 'pdf'], string $titulo = 'Reporte SEO octubre'): Reporte
    {
        $reporte = Reporte::create([
            'cliente_id' => $cliente->id,
            'area' => AreaReporte::Seo->value,
            'titulo' => $titulo,
            'periodo_inicio' => '2026-10-01',
            'periodo_fin' => '2026-10-31',
            'estado' => $estado === 'borrador' ? EstadoReporte::Borrador->value : $estado,
            'numero' => 'REP-SEO-2026-'.str_pad((string) ++$this->folioPortal, 4, '0', STR_PAD_LEFT),
            'notas_alcance' => 'NOTA-DE-ALCANCE-INTERNA',
            'fuentes' => ['FUENTE-INTERNA'],
        ]);

        $reporte->secciones()->create([
            'tipo' => 'resumen_cliente', 'titulo' => 'Resumen', 'orden' => 1, 'visible' => true,
            'contenido' => ['logros' => [['texto' => 'Subió el tráfico.']], 'retos' => [], 'proximos_pasos' => []],
        ]);
        $reporte->secciones()->create([
            'tipo' => 'texto', 'titulo' => 'Pagos', 'orden' => 2, 'visible' => true, 'interno' => true,
            'contenido' => ['formato' => 'pares', 'bloques' => [['titulo' => 'Tarjeta', 'cuerpo' => 'SECCION-INTERNA-SECRETA']]],
        ]);

        foreach ($formatos as $i => $formato) {
            $this->entrega($reporte, $formato, $i + 1);
        }

        return $reporte->fresh();
    }

    protected function entrega(Reporte $reporte, string $formato, int $version = 1, ?Carbon $creada = null): ReporteEntrega
    {
        $extension = $formato === 'xlsx' ? 'xlsx' : 'pdf';
        $ruta = "clientes/{$reporte->cliente_id}/reportes/{$reporte->numero}-{$formato}-v{$version}.{$extension}";
        $contenido = "%PDF-CONTENIDO-{$formato}-{$reporte->numero}-v{$version}";
        Storage::disk('local')->put($ruta, $contenido);

        $archivo = Archivo::create([
            'cliente_id' => $reporte->cliente_id, 'nombre' => basename($ruta), 'tipo' => 'reporte',
            'ruta_archivo' => $ruta, 'tamano' => strlen($contenido), 'extension' => $extension, 'subido_por' => null,
        ]);

        $entrega = ReporteEntrega::create([
            'reporte_id' => $reporte->id, 'archivo_id' => $archivo->id, 'formato' => $formato, 'version' => $version, 'generado_por' => null,
        ]);
        if ($creada !== null) {
            $entrega->forceFill(['created_at' => $creada])->save();
        }

        return $entrega;
    }

    protected function propuesta(Cliente $cliente, string $estado = 'enviada', array $extra = []): Propuesta
    {
        return Propuesta::create(Plantilla::vacio() + $extra + [
            'cliente_id' => $cliente->id,
            'titulo' => 'Propuesta de Continuidad SEO',
            'estado' => $estado,
            'folio' => 'PROP-CONT-2026-'.str_pad((string) ++$this->folioPortal, 4, '0', STR_PAD_LEFT),
            'fecha_emision' => '2026-11-01',
            'precio_mensual' => 98765.43,
        ]);
    }

    protected function metrica(Cliente $cliente, string $nombre, ?float $meta, ?float $valor, array $extra = []): MetricaCliente
    {
        $metrica = MetricaCliente::create($extra + [
            'cliente_id' => $cliente->id, 'nombre' => $nombre, 'unidad' => 'llamadas', 'meta' => $meta,
            'sentido' => 'mayor', 'umbral_amarillo_pct' => 80, 'activa' => true, 'orden' => 1,
        ]);

        if ($valor !== null) {
            MetricaClienteValor::create(['metrica_id' => $metrica->id, 'periodo' => '2026-11-01', 'valor' => $valor, 'nota' => 'NOTA-DE-METRICA', 'registrado_por' => null]);
        }

        return $metrica;
    }
}
