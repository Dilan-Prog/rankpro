<?php

namespace App\Http\Controllers\Api\V1\Ads;

use App\Models\AdsCampana;
use App\Models\AdsGrupo;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;

class AdsGrupoApiController
{
    public function index(AdsCampana $campana)
    {
        return Respuesta::coleccion(Serializador::coleccion($campana->grupos()->with('keywords', 'columnasPersonalizadas')->get(), ['keywords', 'columnasPersonalizadas']));
    }

    public function show(AdsGrupo $grupo)
    {
        return Respuesta::recurso(Serializador::modelo($grupo->load('keywords', 'columnasPersonalizadas'), ['keywords', 'columnasPersonalizadas']));
    }

    public function store(Request $request, AdsCampana $campana)
    {
        $data = $this->validated($request);

        $grupo = $campana->grupos()->create($data);

        return Respuesta::recurso(Serializador::modelo($grupo->load('keywords', 'columnasPersonalizadas'), ['keywords', 'columnasPersonalizadas']), 201);
    }

    public function update(Request $request, AdsGrupo $grupo)
    {
        $data = $this->validated($request);

        $grupo->update($data);

        return Respuesta::recurso(Serializador::modelo($grupo->fresh()->load('keywords', 'columnasPersonalizadas'), ['keywords', 'columnasPersonalizadas']));
    }

    public function destroy(AdsGrupo $grupo)
    {
        $grupo->delete();

        return Respuesta::eliminado();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'audiencia' => ['nullable', 'string', 'max:255'],
            'presupuesto' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'in:activo,pausado'],
        ]);

        $data['presupuesto'] = $data['presupuesto'] ?? 0;

        return $data;
    }
}
