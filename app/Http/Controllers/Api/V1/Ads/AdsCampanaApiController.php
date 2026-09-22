<?php

namespace App\Http\Controllers\Api\V1\Ads;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\AdsCampana;
use App\Support\Api\{ConsultaOpciones, Respuesta, Serializador};
use App\Support\Reglas\Ads as ReglasAds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdsCampanaApiController extends ControladorApi
{
    private const INCLUIBLES = ['grupos', 'creativos', 'metricas', 'optimizaciones', 'cliente', 'servicio'];

    public function index(Request $request)
    {
        return $this->listar(AdsCampana::query(), $request, new ConsultaOpciones(
            buscarEn: ['nombre'],
            filtrosExactos: ['cliente_id', 'servicio_id', 'plataforma', 'objetivo', 'estado', 'fase_actual'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
            incluibles: self::INCLUIBLES,
        ));
    }

    public function show(Request $request, AdsCampana $campana)
    {
        $incluir = array_values(array_intersect(
            array_filter(explode(',', (string) $request->string('incluir'))),
            self::INCLUIBLES
        ));

        if ($incluir) {
            $campana->load($incluir);
        }

        return Respuesta::recurso(Serializador::modelo($campana, $incluir));
    }

    public function store(Request $request)
    {
        $data = $request->validate(ReglasAds::campana());

        $campana = DB::transaction(function () use ($data) {
            $campana = AdsCampana::create([
                'cliente_id' => $data['cliente_id'],
                'servicio_id' => $data['servicio_id'],
                'nombre' => $data['nombre'],
                'plataforma' => $data['plataforma'],
                'objetivo' => $data['objetivo'],
                'presupuesto_mensual' => $data['presupuesto_mensual'],
                'estado' => 'activa',
                'fase_actual' => \App\Enums\FaseAds::Briefing->value,
                'ciclo_actual' => 1,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
            ]);

            $campana->briefings()->create([
                'ciclo' => 1,
                'checklist' => collect(array_keys(\App\Models\AdsBriefing::CHECKLIST))->mapWithKeys(fn ($key) => [$key => false])->all(),
            ]);
            $campana->configuraciones()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->lanzamientos()->create(['ciclo' => 1, 'checklist' => []]);
            $campana->reportes()->create(['ciclo' => 1, 'checklist' => []]);

            return $campana;
        });

        return Respuesta::recurso(Serializador::modelo($campana->fresh()), 201);
    }

    public function update(Request $request, AdsCampana $campana)
    {
        $data = $request->validate(ReglasAds::campana($campana));

        $campana->update($data);

        return Respuesta::recurso(Serializador::modelo($campana->fresh()));
    }

    public function destroy(AdsCampana $campana)
    {
        $campana->delete();

        return Respuesta::eliminado();
    }
}
