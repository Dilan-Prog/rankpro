<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\SeoCampana;
use App\Models\SeoOnPageAccion;
use App\Support\Api\{ConsultaOpciones, Respuesta, Serializador};
use Illuminate\Http\Request;

class SeoOnPageApiController extends ControladorApi
{
    public function index(Request $request, SeoCampana $campana)
    {
        return $this->listar($campana->onPageAcciones(), $request, new ConsultaOpciones(
            buscarEn: ['url_pagina', 'accion'],
            filtrosExactos: ['estado', 'responsable_id'],
            ordenables: ['id', 'fecha', 'created_at', 'updated_at'],
            incluibles: ['responsable'],
        ));
    }

    public function store(Request $request, SeoCampana $campana)
    {
        $accion = $campana->onPageAcciones()->create($this->validado($request));

        return Respuesta::recurso(Serializador::modelo($accion->load('responsable'), ['responsable']), 201);
    }

    public function update(Request $request, SeoOnPageAccion $accion)
    {
        $accion->update($this->validado($request));

        return Respuesta::recurso(Serializador::modelo($accion->fresh()->load('responsable'), ['responsable']));
    }

    public function destroy(SeoOnPageAccion $accion)
    {
        $accion->delete();

        return Respuesta::eliminado();
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'url_pagina' => ['required', 'string', 'max:255'],
            'accion' => ['required', 'string', 'max:2000'],
            'fecha' => ['nullable', 'date'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'estado' => ['required', 'in:en_progreso,completada,pausada'],
        ]);
    }
}
