<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoCampana;
use App\Models\SeoOnPageAccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoOnPageAccionController extends Controller
{
    public function store(Request $request, SeoCampana $campana): JsonResponse
    {
        $accion = $campana->onPageAcciones()->create($this->validated($request));

        return response()->json($accion->load('responsable'), 201);
    }

    public function update(Request $request, SeoOnPageAccion $accion): JsonResponse
    {
        $accion->update($this->validated($request));

        return response()->json($accion->fresh()->load('responsable'));
    }

    public function destroy(SeoOnPageAccion $accion): JsonResponse
    {
        $accion->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
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
