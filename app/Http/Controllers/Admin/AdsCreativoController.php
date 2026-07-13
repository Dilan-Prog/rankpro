<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsCampana;
use App\Models\AdsCreativo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsCreativoController extends Controller
{
    public function store(Request $request, AdsCampana $campana): JsonResponse
    {
        $data = $this->validated($request);

        $creativo = $campana->creativos()->create($data);

        return response()->json($creativo, 201);
    }

    public function update(Request $request, AdsCreativo $creativo): JsonResponse
    {
        $data = $this->validated($request);

        $creativo->update($data);

        return response()->json($creativo->fresh());
    }

    public function destroy(AdsCreativo $creativo): JsonResponse
    {
        $creativo->delete();

        return response()->json(['deleted' => true]);
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
