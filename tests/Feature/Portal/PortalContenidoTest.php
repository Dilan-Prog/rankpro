<?php

namespace Tests\Feature\Portal;

use Illuminate\Support\Facades\Cache;
use App\Models\Archivo;
use App\Models\Finanza;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lista blanca por pantalla: ningún dato interno sale en el HTML ni se puede descargar, y solo lo
 * que el cliente debe ver (reportes listos/entregados, propuestas enviadas/aprobadas, métricas activas).
 */
class PortalContenidoTest extends TestCase
{
    use PreparaPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPortal();
        RateLimiter::clear('portal|127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->cerrarPortal();
        parent::tearDown();
    }

    /** @return array<string, string> html por pantalla */
    private function pantallas(string $token): array
    {
        $html = [];
        foreach (['inicio', 'reportes', 'propuestas', 'metricas'] as $pantalla) {
            $html[$pantalla] = $this->get($this->ruta('portal.'.$pantalla, $token))->assertOk()->getContent();
        }

        return $html;
    }

    public function test_ningun_dato_interno_aparece_en_ninguna_pantalla(): void
    {
        $cliente = $this->cliente('Hotel Fratelli', ['notas' => 'NOTA-INTERNA-DEL-CLIENTE', 'email' => 'interno-secreto@ejemplo.test']);
        $cliente->forceFill(['api_token' => 'TOKEN-DE-TRACKING-SECRETO'])->save();
        [, $token] = $this->enlace($cliente);

        $this->reporte($cliente);
        $this->propuesta($cliente);
        $this->metrica($cliente, 'Llamadas', 10, 12);
        $servicio = Servicio::factory()->create(['cliente_id' => $cliente->id, 'nombre' => 'SERVICIO-CON-PRECIO', 'precio_mensual' => 54321.00]);
        Finanza::create(['cliente_id' => $cliente->id, 'servicio_id' => $servicio->id, 'concepto' => 'FINANZA-SECRETA', 'tipo' => 'ingreso', 'monto' => 77777, 'estado' => 'pendiente', 'mes' => 11, 'anio' => 2026]);
        Storage::disk('local')->put('clientes/'.$cliente->id.'/archivos/contrato.pdf', 'CONTRATO-CONFIDENCIAL');
        Archivo::create(['cliente_id' => $cliente->id, 'nombre' => 'CONTRATO-FIRMADO.pdf', 'tipo' => 'contrato', 'ruta_archivo' => 'clientes/'.$cliente->id.'/archivos/contrato.pdf', 'tamano' => 10, 'extension' => 'pdf']);

        $prohibidos = [
            'SECCION-INTERNA-SECRETA', 'NOTA-DE-ALCANCE-INTERNA', 'FUENTE-INTERNA', 'NOTA-INTERNA-DEL-CLIENTE', 'interno-secreto@ejemplo.test',
            'TOKEN-DE-TRACKING-SECRETO', 'SERVICIO-CON-PRECIO', 'FINANZA-SECRETA', '54321', '77777', 'CONTRATO-FIRMADO', 'CONTRATO-CONFIDENCIAL',
            '98765', 'NOTA-DE-METRICA', 'registrado_por', 'api_token',
        ];
        foreach ($this->pantallas($token) as $pantalla => $html) {
            foreach ($prohibidos as $texto) {
                $this->assertStringNotContainsString($texto, $html, "La pantalla {$pantalla} expone «{$texto}»");
            }
        }
    }

    public function test_un_contrato_de_archivos_no_se_puede_descargar_con_ningun_id(): void
    {
        $cliente = $this->cliente();
        [, $token] = $this->enlace($cliente);
        $this->reporte($cliente);
        $this->propuesta($cliente);
        Storage::disk('local')->put('clientes/'.$cliente->id.'/archivos/contrato.pdf', 'CONTRATO-CONFIDENCIAL');
        $contrato = Archivo::create(['cliente_id' => $cliente->id, 'nombre' => 'c.pdf', 'tipo' => 'contrato', 'ruta_archivo' => 'clientes/'.$cliente->id.'/archivos/contrato.pdf', 'tamano' => 10, 'extension' => 'pdf']);

        // Los ids crecen durante la suite completa: se prueba una ventana acotada (el limite del portal es 60/min).
        foreach (range(max(1, $contrato->id - 10), $contrato->id + 10) as $id) {
            $r = $this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => $id]));
            $this->assertStringNotContainsString('CONTRATO-CONFIDENCIAL', $r->isOk() ? $r->streamedContent() : (string) $r->getContent());
        }
        // No existe ninguna ruta de descarga por Archivo.
        Cache::flush();
        $this->get('/portal/'.$token.'/archivos/'.$contrato->id)->assertNotFound();
        $this->get('/portal/'.$token.'/descargar/'.$contrato->id)->assertNotFound();
    }

    public function test_reportes_solo_listos_o_entregados_y_solo_pdf(): void
    {
        $cliente = $this->cliente();
        [, $token] = $this->enlace($cliente);
        $borrador = $this->reporte($cliente, 'borrador', ['pdf_cliente', 'pdf'], 'REPORTE-BORRADOR');
        $listo = $this->reporte($cliente, 'listo', ['pdf_cliente', 'xlsx'], 'REPORTE-LISTO');
        $entregado = $this->reporte($cliente, 'entregado', ['pdf'], 'REPORTE-ENTREGADO');

        $html = $this->get($this->ruta('portal.reportes', $token))->assertOk()->getContent();

        $this->assertStringNotContainsString('REPORTE-BORRADOR', $html);
        $this->assertStringContainsString('REPORTE-LISTO', $html);
        $this->assertStringContainsString('REPORTE-ENTREGADO', $html);
        $this->assertSame(2, substr_count($html, 'data-reporte-entrega='), 'solo las entregas pdf_cliente del listo y pdf del entregado');
        $this->assertStringContainsString('Resumen para el cliente (PDF)', $html);

        // El borrador y el XLSX no se descargan aunque se adivine el id.
        foreach ($borrador->entregas as $e) {
            $this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => $e->id]))->assertNotFound();
        }
        $xlsx = $listo->entregas()->where('formato', 'xlsx')->firstOrFail();
        $this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => $xlsx->id]))->assertNotFound();
    }

    public function test_un_reporte_eliminado_no_aparece_ni_se_descarga(): void
    {
        $cliente = $this->cliente();
        [, $token] = $this->enlace($cliente);
        $reporte = $this->reporte($cliente, 'entregado', ['pdf_cliente']);
        $entrega = $reporte->entregas()->firstOrFail();
        $reporte->delete();

        $this->assertStringNotContainsString($reporte->titulo, $this->get($this->ruta('portal.reportes', $token))->getContent());
        $this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => $entrega->id]))->assertNotFound();
    }

    public function test_la_descarga_entrega_el_archivo_con_el_nombre_del_entregable(): void
    {
        $cliente = $this->cliente('Hotel Fratelli');
        [, $token] = $this->enlace($cliente);
        $reporte = $this->reporte($cliente, 'entregado', ['pdf_cliente', 'pdf']);
        $cliente = $reporte->cliente;

        $entrega = $reporte->entregas()->where('formato', 'pdf_cliente')->firstOrFail();
        $respuesta = $this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => $entrega->id]))->assertOk();

        $this->assertSame("%PDF-CONTENIDO-pdf_cliente-{$reporte->numero}-v1", $respuesta->streamedContent());
        $this->assertStringContainsString("hotel-fratelli_2026-10_{$reporte->numero}-cliente-v1.pdf", $respuesta->headers->get('Content-Disposition'));
    }

    public function test_el_pdf_completo_no_se_ofrece_si_una_seccion_se_marco_interna_despues(): void
    {
        $cliente = $this->cliente();
        [, $token] = $this->enlace($cliente);
        $reporte = $this->reporte($cliente, 'entregado', ['pdf_cliente']);
        // PDF completo generado ANTES de que se marcara interna la sección «Pagos».
        $pdf = $this->entrega($reporte, 'pdf', 1, now()->subDay());
        $reporte->secciones()->where('interno', true)->update(['updated_at' => now()]);

        $this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => $pdf->id]))->assertNotFound();
        $this->assertSame(1, substr_count($this->get($this->ruta('portal.reportes', $token))->getContent(), 'data-reporte-entrega='));

        // El resumen para el cliente no depende de las demás secciones: sigue disponible.
        $cliente_pdf = $reporte->entregas()->where('formato', 'pdf_cliente')->firstOrFail();
        $this->get($this->ruta('portal.reportes.descargar', $token, ['entrega' => $cliente_pdf->id]))->assertOk();
    }

    public function test_propuestas_solo_enviadas_o_aprobadas_y_sin_borradores(): void
    {
        $cliente = $this->cliente();
        [, $token] = $this->enlace($cliente);
        $borrador = $this->propuesta($cliente, 'borrador', ['titulo' => 'PROPUESTA-BORRADOR']);
        $rechazada = $this->propuesta($cliente, 'rechazada', ['titulo' => 'PROPUESTA-RECHAZADA']);
        $enviada = $this->propuesta($cliente, 'enviada', ['titulo' => 'PROPUESTA-ENVIADA']);
        $aprobada = $this->propuesta($cliente, 'aprobada', ['titulo' => 'PROPUESTA-APROBADA']);
        $sinFolio = $this->propuesta($cliente, 'enviada', ['titulo' => 'PROPUESTA-SIN-FOLIO', 'folio' => null]);

        $html = $this->get($this->ruta('portal.propuestas', $token))->assertOk()->getContent();

        $this->assertStringNotContainsString('PROPUESTA-BORRADOR', $html);
        $this->assertStringNotContainsString('PROPUESTA-RECHAZADA', $html);
        foreach (['PROPUESTA-ENVIADA', 'PROPUESTA-APROBADA', 'PROPUESTA-SIN-FOLIO'] as $visible) {
            $this->assertStringContainsString($visible, $html);
        }
        $this->assertSame(2, substr_count($html, 'Descargar PDF'), 'sin folio no hay PDF que ofrecer');

        foreach ([$borrador, $rechazada, $sinFolio] as $oculta) {
            $this->get($this->ruta('portal.propuestas.descargar', $token, ['propuesta' => $oculta->id]))->assertNotFound();
        }
        $pdf = $this->get($this->ruta('portal.propuestas.descargar', $token, ['propuesta' => $enviada->id]))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString($enviada->folio.'.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertNotNull($aprobada);
    }

    public function test_metricas_activas_con_semaforo_y_sin_meta_nunca_verde(): void
    {
        $cliente = $this->cliente();
        [, $token] = $this->enlace($cliente);
        $this->metrica($cliente, 'METRICA-VERDE', 10, 12, ['orden' => 1]);
        $this->metrica($cliente, 'METRICA-ROJA', 100, 10, ['orden' => 2]);
        $this->metrica($cliente, 'METRICA-SIN-META', null, 55, ['orden' => 3]);
        $this->metrica($cliente, 'METRICA-SIN-DATO', 10, null, ['orden' => 4]);
        $this->metrica($cliente, 'METRICA-INACTIVA', 10, 12, ['orden' => 5, 'activa' => false]);

        $html = $this->get($this->ruta('portal.metricas', $token))->assertOk()->getContent();

        $this->assertStringNotContainsString('METRICA-INACTIVA', $html);
        $porMetrica = [];
        foreach (explode('<article', $html) as $bloque) {
            if (preg_match('/METRICA-[A-Z-]+/', $bloque, $m) === 1) {
                $porMetrica[$m[0]] = $bloque;
            }
        }
        $this->assertCount(4, $porMetrica);
        $this->assertStringContainsString('portal-semaforo--verde', $porMetrica['METRICA-VERDE']);
        $this->assertStringContainsString('portal-semaforo--rojo', $porMetrica['METRICA-ROJA']);
        $this->assertStringContainsString('portal-semaforo--neutro', $porMetrica['METRICA-SIN-META']);
        $this->assertStringNotContainsString('portal-semaforo--verde', $porMetrica['METRICA-SIN-META']);
        $this->assertStringContainsString('Sin meta', $porMetrica['METRICA-SIN-META']);
        $this->assertStringContainsString('portal-semaforo--neutro', $porMetrica['METRICA-SIN-DATO']);
        // Valor actual y los 6 meses del historial.
        $this->assertStringContainsString('<strong>12</strong>', $porMetrica['METRICA-VERDE']);
        $this->assertSame(6, substr_count($porMetrica['METRICA-VERDE'], '<th scope="col">'));
        $this->assertStringContainsString('nov 2026', $porMetrica['METRICA-VERDE']);
    }

    public function test_el_semaforo_general_del_inicio_no_es_verde_sin_metas(): void
    {
        $cliente = $this->cliente();
        [, $token] = $this->enlace($cliente);
        $this->metrica($cliente, 'Sin meta', null, 55);

        $html = $this->get($this->ruta('portal.inicio', $token))->assertOk()->getContent();
        $this->assertStringNotContainsString('portal-semaforo--verde', $html);
        $this->assertStringContainsString('portal-semaforo--neutro', $html);

        $this->metrica($cliente, 'Con meta roja', 100, 1, ['orden' => 2]);
        $this->assertStringContainsString('portal-semaforo--rojo', $this->get($this->ruta('portal.inicio', $token))->getContent());
    }

    public function test_el_inicio_muestra_nombre_ultimos_reportes_y_vigencia(): void
    {
        $cliente = $this->cliente('Hotel Fratelli');
        [, $token] = $this->enlace($cliente, 7);
        foreach (['R1', 'R2', 'R3', 'R4'] as $titulo) {
            $this->reporte($cliente, 'entregado', ['pdf_cliente'], "REPORTE-{$titulo}");
        }

        $html = $this->get($this->ruta('portal.inicio', $token))->assertOk()->getContent();

        $this->assertStringContainsString('Hotel Fratelli', $html);
        $this->assertSame(3, substr_count($html, 'data-reporte-entrega='), 'solo los 3 más recientes');
        $this->assertStringContainsString('18/11/2026', $html);
    }

    public function test_el_texto_del_cliente_se_escapa(): void
    {
        $cliente = $this->cliente('<script>alert(1)</script>');
        [, $token] = $this->enlace($cliente);

        $html = $this->get($this->ruta('portal.inicio', $token))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
