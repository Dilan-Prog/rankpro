<?php

namespace App\Http\Controllers\Api\V1\Desarrollo;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoComunicacion;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bitácora de comunicaciones de un proyecto. Mismas reglas que Admin\ComunicacionController. */
class ComunicacionesApiController extends Controller
{
    public function index(Proyecto $proyecto): JsonResponse
    {
        return Respuesta::coleccion(Serializador::coleccion($proyecto->comunicaciones()->orderByDesc('fecha')->get()));
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'resumen' => ['required', 'string', 'max:2000'],
            'aprobaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $comunicacion = $proyecto->comunicaciones()->create($data);

        return Respuesta::recurso(Serializador::modelo($comunicacion), 201);
    }

    public function destroy(ProyectoComunicacion $comunicacion): JsonResponse
    {
        $comunicacion->delete();

        return Respuesta::eliminado();
    }
}
