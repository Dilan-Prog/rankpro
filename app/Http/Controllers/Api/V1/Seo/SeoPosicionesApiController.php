<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\SeoCampana;
use App\Models\SeoPosicion;
use App\Support\Api\{ConsultaOpciones, Respuesta};
use Illuminate\Http\Request;

class SeoPosicionesApiController extends ControladorApi
{
    public function index(Request $request, SeoCampana $campana)
    {
        return $this->listar($campana->posiciones(), $request, new ConsultaOpciones(
            buscarEn: ['keyword', 'url_pagina'],
            filtrosExactos: ['dispositivo', 'pais'],
            ordenables: ['id', 'keyword', 'posicion_actual', 'fecha_registro', 'created_at', 'updated_at'],
        ));
    }

    public function store(Request $request, SeoCampana $campana)
    {
        $data = $this->validado($request);

        $data['cliente_id'] = $campana->cliente_id;
        $data['variacion'] = ($data['posicion_anterior'] ?? 0) - ($data['posicion_actual'] ?? 0);
        $data['fecha_registro'] = now()->toDateString();

        $posicion = $campana->posiciones()->create($data);

        return Respuesta::recurso($posicion, 201);
    }

    public function destroy(SeoPosicion $posicion)
    {
        $posicion->delete();

        return Respuesta::eliminado();
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'url_pagina' => ['nullable', 'string', 'max:255'],
            'posicion_actual' => ['nullable', 'integer', 'min:0'],
            'posicion_anterior' => ['nullable', 'integer', 'min:0'],
            'volumen_busqueda' => ['nullable', 'integer', 'min:0'],
            'dificultad_keyword' => ['nullable', 'integer', 'min:0', 'max:100'],
            'dispositivo' => ['required', 'in:mobile,desktop'],
            'pais' => ['nullable', 'string', 'max:10'],
        ]);
    }
}
