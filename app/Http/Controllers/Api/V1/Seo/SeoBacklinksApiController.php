<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\SeoBacklink;
use App\Models\SeoCampana;
use App\Support\Api\{ConsultaOpciones, Respuesta};
use Illuminate\Http\Request;

class SeoBacklinksApiController extends ControladorApi
{
    public function index(Request $request, SeoCampana $campana)
    {
        return $this->listar($campana->backlinks(), $request, new ConsultaOpciones(
            buscarEn: ['url_origen', 'url_destino'],
            filtrosExactos: ['tipo', 'estado'],
            ordenables: ['id', 'da_dr', 'fecha_conseguido', 'created_at', 'updated_at'],
        ));
    }

    public function store(Request $request, SeoCampana $campana)
    {
        $data = $this->validado($request);
        $data['cliente_id'] = $campana->cliente_id;

        $backlink = $campana->backlinks()->create($data);

        return Respuesta::recurso($backlink, 201);
    }

    public function destroy(SeoBacklink $backlink)
    {
        $backlink->delete();

        return Respuesta::eliminado();
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'url_origen' => ['required', 'string', 'max:255'],
            'url_destino' => ['required', 'string', 'max:255'],
            'da_dr' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tipo' => ['required', 'in:dofollow,nofollow'],
            'estado' => ['required', 'in:activo,caido'],
            'fecha_conseguido' => ['nullable', 'date'],
        ]);
    }
}
