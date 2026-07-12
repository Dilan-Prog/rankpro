<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoBacklink;
use App\Models\SeoCampana;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoBacklinkController extends Controller
{
    public function store(Request $request, SeoCampana $campana): JsonResponse
    {
        $data = $request->validate([
            'url_origen' => ['required', 'string', 'max:255'],
            'url_destino' => ['required', 'string', 'max:255'],
            'da_dr' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tipo' => ['required', 'in:dofollow,nofollow'],
            'estado' => ['required', 'in:activo,caido'],
            'fecha_conseguido' => ['nullable', 'date'],
        ]);

        $data['cliente_id'] = $campana->cliente_id;

        $backlink = $campana->backlinks()->create($data);

        return response()->json($backlink, 201);
    }

    public function destroy(SeoBacklink $backlink): JsonResponse
    {
        $backlink->delete();

        return response()->json(['deleted' => true]);
    }
}
