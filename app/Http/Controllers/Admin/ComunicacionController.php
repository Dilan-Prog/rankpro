<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoComunicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComunicacionController extends Controller
{
    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'resumen' => ['required', 'string', 'max:2000'],
            'aprobaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $comunicacion = $proyecto->comunicaciones()->create($data);

        return response()->json($comunicacion, 201);
    }

    public function destroy(ProyectoComunicacion $comunicacion): JsonResponse
    {
        $comunicacion->delete();

        return response()->json(['deleted' => true]);
    }
}
