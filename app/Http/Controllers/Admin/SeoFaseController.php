<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseSeo;
use App\Exceptions\ErrorDeFase;
use App\Http\Controllers\Controller;
use App\Models\SeoCampana;
use App\Models\SeoFaseAuditoria;
use App\Services\Fases\MaquinaSeo;
use App\Support\Reglas\SeoFases as ReglasSeoFases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeoFaseController extends Controller
{
    public function __construct(private MaquinaSeo $maquina)
    {
    }

    /**
     * Autosave endpoint for the current phase's panel. Every phase row is
     * cycle-scoped (seo_campanas.ciclo_actual), and the *Actual relations
     * on SeoCampana (ofMany 'ciclo','max') already resolve to the row for
     * the current cycle, so this never needs to filter by ciclo manually.
     */
    public function guardar(Request $request, SeoCampana $campana): JsonResponse
    {
        $fase = $campana->fase_actual;

        if ($fase === FaseSeo::Cerrada) {
            return response()->json(['message' => 'Esta campaña está cerrada.'], 422);
        }

        $data = $request->validate(ReglasSeoFases::guardar($fase));

        // Independent of the per-phase fields above and of the campaign's current phase — the
        // Técnico tab is editable regardless of which phase the campaign is sitting in today, so
        // this is validated/persisted separately rather than folded into the $fase match above.
        // Never read by checklistKeys()/checklistCompleto()/aprobar(), always stored on the
        // current cycle's Auditoria row.
        $tecnico = $request->validate(ReglasSeoFases::tecnico());

        $registro = $this->maquina->registro($campana, $fase);

        if (array_key_exists('checklist', $data)) {
            $data['checklist'] = $this->maquina->fusionarChecklist($registro, $fase, $data['checklist']);
        }

        // Boolean fields are only present in $data when checked (HTML checkboxes omit unchecked ones),
        // so any boolean field the phase supports needs an explicit false fallback via $request->boolean().
        foreach (ReglasSeoFases::booleanos($fase) as $field) {
            $data[$field] = $request->boolean($field);
        }

        $registro->update($data);

        // $registro already IS the Auditoria row when $fase is Auditoria — reuse it directly
        // rather than re-reading $campana->faseAuditoria, whose relation cache registroFase()
        // may have already populated with a stale null before the row above was created.
        $auditoria = $fase === FaseSeo::Auditoria
            ? $registro
            : ($campana->faseAuditoria ?? $campana->auditorias()->create(['ciclo' => $campana->ciclo_actual, 'checklist' => []]));
        if (array_key_exists('tecnico_checklist', $tecnico)) {
            $keys = array_keys(collect(SeoFaseAuditoria::TECNICO_CHECKLIST)->collapse()->all());
            $auditoria->update([
                'tecnico_checklist' => collect($keys)
                    ->mapWithKeys(fn ($key) => [$key => (bool) ($tecnico['tecnico_checklist'][$key] ?? $auditoria->tecnico_checklist[$key] ?? false)])
                    ->all(),
            ]);
        }

        return response()->json([
            'checklist' => $registro->fresh()->checklist,
            'tecnico_checklist' => $auditoria->fresh()->tecnico_checklist,
            'completo' => $this->maquina->checklistCompleto($registro->fresh(), $fase),
        ]);
    }

    public function aprobar(SeoCampana $campana): RedirectResponse
    {
        try {
            $r = $this->maquina->aprobar($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.seo.show', $campana)->with('status', $r->mensaje);
    }

    public function retroceder(SeoCampana $campana): RedirectResponse
    {
        try {
            $r = $this->maquina->retroceder($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.seo.show', $campana)->with('status', $r->mensaje);
    }

    public function nuevoCiclo(SeoCampana $campana): RedirectResponse
    {
        try {
            $r = $this->maquina->nuevoCiclo($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.seo.show', $campana)->with('status', $r->mensaje.' La campaña volvió a fase de Auditoría.');
    }

    public function cerrar(SeoCampana $campana): RedirectResponse
    {
        try {
            $this->maquina->cerrar($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.seo.show', $campana)->with('status', 'Campaña cerrada.');
    }

    public function pausar(SeoCampana $campana): RedirectResponse
    {
        try {
            $this->maquina->pausar($campana);
        } catch (ErrorDeFase $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        return redirect()->route('admin.seo.show', $campana)->with('status', 'Campaña pausada.');
    }
}
