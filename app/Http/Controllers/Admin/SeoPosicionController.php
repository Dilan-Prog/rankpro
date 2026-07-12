<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoCampana;
use App\Models\SeoPosicion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoPosicionController extends Controller
{
    public function store(Request $request, SeoCampana $campana): JsonResponse
    {
        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'url_pagina' => ['nullable', 'string', 'max:255'],
            'posicion_actual' => ['nullable', 'integer', 'min:0'],
            'posicion_anterior' => ['nullable', 'integer', 'min:0'],
            'volumen_busqueda' => ['nullable', 'integer', 'min:0'],
            'dificultad_keyword' => ['nullable', 'integer', 'min:0', 'max:100'],
            'dispositivo' => ['required', 'in:mobile,desktop'],
            'pais' => ['nullable', 'string', 'max:10'],
        ]);

        $data['cliente_id'] = $campana->cliente_id;
        $data['variacion'] = ($data['posicion_anterior'] ?? 0) - ($data['posicion_actual'] ?? 0);
        $data['fecha_registro'] = now()->toDateString();

        $posicion = $campana->posiciones()->create($data);

        return response()->json($posicion, 201);
    }

    public function destroy(SeoPosicion $posicion): JsonResponse
    {
        $posicion->delete();

        return response()->json(['deleted' => true]);
    }
}
