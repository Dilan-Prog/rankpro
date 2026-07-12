<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bug;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BugController extends Controller
{
    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $data = $this->validated($request);

        $bug = $proyecto->bugs()->create($data);

        return response()->json($bug, 201);
    }

    public function update(Request $request, Bug $bug): JsonResponse
    {
        $data = $this->validated($request);

        $bug->update($data);

        return response()->json($bug->fresh());
    }

    public function destroy(Bug $bug): JsonResponse
    {
        $bug->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'prioridad' => ['required', 'in:alta,media,baja'],
            'estado' => ['required', 'in:abierto,en_progreso,resuelto'],
            'fecha_resolucion' => ['nullable', 'date'],
        ]);
    }
}
