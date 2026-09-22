<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Enums\FaseSeo;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\SeoCampana;
use App\Support\Api\{ConsultaOpciones, Respuesta, Serializador};
use App\Support\Reglas\SeoCampanas as ReglasSeoCampanas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeoCampanasApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(SeoCampana::query(), $request, new ConsultaOpciones(
            buscarEn: ['nombre', 'url_sitio'],
            filtrosExactos: ['cliente_id', 'servicio_id', 'estado', 'fase_actual'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
            incluibles: ['posiciones', 'backlinks', 'contenido', 'onPageAcciones', 'metricasMensuales'],
        ));
    }

    /**
     * `?incluir=posiciones,backlinks,contenido,onpage,metricas` — los nombres
     * cortos del contrato se traducen aquí a las relaciones reales del
     * modelo (onPageAcciones/metricasMensuales) antes de cargarlas.
     */
    public function show(Request $request, SeoCampana $campana)
    {
        $mapa = [
            'posiciones' => 'posiciones',
            'backlinks' => 'backlinks',
            'contenido' => 'contenido',
            'onpage' => 'onPageAcciones',
            'metricas' => 'metricasMensuales',
        ];

        $pedidos = array_filter(explode(',', (string) $request->string('incluir')));
        $relaciones = array_values(array_intersect_key($mapa, array_flip($pedidos)));

        if ($relaciones) {
            $campana->load($relaciones);
        }

        return Respuesta::recurso(Serializador::modelo($campana, $relaciones));
    }

    public function store(Request $request)
    {
        $data = $request->validate(ReglasSeoCampanas::crear());

        // Misma forma que SeoController::store() (web): una campaña nueva
        // nace en ciclo 1 / fase Auditoría con las 4 filas de fase vacías ya
        // creadas, para que el panel y la API arranquen desde el mismo punto.
        $campana = DB::transaction(function () use ($data) {
            $campana = SeoCampana::create([
                'cliente_id' => $data['cliente_id'],
                'servicio_id' => $data['servicio_id'],
                'nombre' => $data['nombre'],
                'url_sitio' => $data['url_sitio'] ?? null,
                'estado' => 'activa',
                'fase_actual' => FaseSeo::Auditoria->value,
                'ciclo_actual' => 1,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
            ]);

            $campana->auditorias()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->estrategias()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->ejecuciones()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

            return $campana;
        });

        return Respuesta::recurso(Serializador::modelo($campana), 201);
    }

    public function update(Request $request, SeoCampana $campana)
    {
        $data = $request->validate(ReglasSeoCampanas::actualizar());

        $campana->update($data);

        return Respuesta::recurso(Serializador::modelo($campana->fresh()));
    }

    public function destroy(SeoCampana $campana)
    {
        $campana->delete();

        return Respuesta::eliminado();
    }
}
