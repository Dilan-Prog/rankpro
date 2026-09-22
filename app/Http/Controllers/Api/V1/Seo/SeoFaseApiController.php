<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Enums\FaseSeo;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Services\Fases\MaquinaSeo;
use App\Support\Api\Respuesta;
use App\Support\Reglas\SeoFases as ReglasSeoFases;
use Illuminate\Http\Request;

/**
 * Equivalente API de SeoFaseController (web). `guardar()` replica el
 * autosave del controlador web (mismas reglas de App\Support\Reglas\SeoFases,
 * mismo uso de MaquinaSeo::registro()/fusionarChecklist()); las transiciones
 * (aprobar/retroceder/...) solo llaman a MaquinaSeo, que ya hace todo el
 * trabajo y lanza ErrorDeFase (422 automático vía el Handler).
 */
class SeoFaseApiController extends ControladorApi
{
    public function __construct(private MaquinaSeo $maquina)
    {
    }

    public function estado(SeoCampana $campana)
    {
        return Respuesta::recurso($this->maquina->estado($campana));
    }

    public function guardar(Request $request, SeoCampana $campana)
    {
        $fase = $campana->fase_actual;

        if ($fase === FaseSeo::Cerrada) {
            return Respuesta::mensaje('Esta campaña está cerrada.', 422);
        }

        $data = $request->validate(ReglasSeoFases::guardar($fase));
        $tecnico = $request->validate(ReglasSeoFases::tecnico());

        $registro = $this->maquina->registro($campana, $fase);

        if (array_key_exists('checklist', $data)) {
            $data['checklist'] = $this->maquina->fusionarChecklist($registro, $fase, $data['checklist']);
        }

        // A diferencia del form web, un JSON de n8n sí distingue "false explícito" de
        // "campo ausente" — pero se mantiene el mismo fallback que el controlador web para
        // que ambos canales de escritura se comporten igual ante un booleano omitido.
        foreach (ReglasSeoFases::booleanos($fase) as $campo) {
            $data[$campo] = $request->boolean($campo);
        }

        $registro->update($data);

        $auditoria = $fase === FaseSeo::Auditoria
            ? $registro
            : ($campana->faseAuditoria ?? $campana->auditorias()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]));

        if (array_key_exists('tecnico_checklist', $tecnico)) {
            $claves = array_keys(collect(SeoFaseAuditoria::TECNICO_CHECKLIST)->collapse()->all());
            $auditoria->update([
                'tecnico_checklist' => collect($claves)
                    ->mapWithKeys(fn ($clave) => [$clave => (bool) ($tecnico['tecnico_checklist'][$clave] ?? $auditoria->tecnico_checklist[$clave] ?? false)])
                    ->all(),
            ]);
        }

        return Respuesta::recurso([
            'checklist' => $registro->fresh()->checklist,
            'tecnico_checklist' => $auditoria->fresh()->tecnico_checklist,
            'completo' => $this->maquina->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(SeoCampana $campana)
    {
        return Respuesta::recurso($this->maquina->aprobar($campana)->toArray());
    }

    public function retroceder(SeoCampana $campana)
    {
        return Respuesta::recurso($this->maquina->retroceder($campana)->toArray());
    }

    public function nuevoCiclo(SeoCampana $campana)
    {
        return Respuesta::recurso($this->maquina->nuevoCiclo($campana)->toArray());
    }

    public function cerrar(SeoCampana $campana)
    {
        return Respuesta::recurso($this->maquina->cerrar($campana)->toArray());
    }

    public function pausar(SeoCampana $campana)
    {
        return Respuesta::recurso($this->maquina->pausar($campana)->toArray());
    }
}
