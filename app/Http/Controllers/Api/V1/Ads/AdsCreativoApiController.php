<?php

namespace App\Http\Controllers\Api\V1\Ads;

use App\Models\AdsCampana;
use App\Models\AdsCreativo;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;

class AdsCreativoApiController
{
    public function index(AdsCampana $campana)
    {
        return Respuesta::coleccion(Serializador::coleccion($campana->creativos()->get()));
    }

    public function store(Request $request, AdsCampana $campana)
    {
        $data = $this->validated($request);

        $creativo = $campana->creativos()->create($data);

        return Respuesta::recurso(Serializador::modelo($creativo), 201);
    }

    public function update(Request $request, AdsCreativo $creativo)
    {
        $data = $this->validated($request);

        $creativo->update($data);

        return Respuesta::recurso(Serializador::modelo($creativo->fresh()));
    }

    public function destroy(AdsCreativo $creativo)
    {
        $creativo->delete();

        return Respuesta::eliminado();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'copy' => ['nullable', 'string', 'max:2000'],
            'tipo' => ['required', 'in:imagen,video,carrusel'],
            'url_creativo' => ['nullable', 'string', 'max:255'],
            'ab_testing' => ['nullable', 'boolean'],
            'estado' => ['required', 'in:activo,pausado'],
        ]);

        $data['ab_testing'] = $request->boolean('ab_testing');

        return $data;
    }
}
