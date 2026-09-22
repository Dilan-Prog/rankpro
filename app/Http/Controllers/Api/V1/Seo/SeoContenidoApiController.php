<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\SeoCampana;
use App\Models\SeoContenido;
use App\Support\Api\{ConsultaOpciones, Respuesta};
use Illuminate\Http\Request;

class SeoContenidoApiController extends ControladorApi
{
    public function index(Request $request, SeoCampana $campana)
    {
        return $this->listar($campana->contenido(), $request, new ConsultaOpciones(
            buscarEn: ['titulo', 'keyword_objetivo', 'url'],
            filtrosExactos: ['estado'],
            ordenables: ['id', 'titulo', 'trafico_generado', 'created_at', 'updated_at'],
        ));
    }

    public function store(Request $request, SeoCampana $campana)
    {
        $contenido = $campana->contenido()->create($this->validado($request));

        return Respuesta::recurso($contenido, 201);
    }

    public function update(Request $request, SeoContenido $contenido)
    {
        $contenido->update($this->validado($request));

        return Respuesta::recurso($contenido->fresh());
    }

    public function destroy(SeoContenido $contenido)
    {
        $contenido->delete();

        return Respuesta::eliminado();
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'keyword_objetivo' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'trafico_generado' => ['nullable', 'integer', 'min:0'],
            'estado' => ['required', 'in:borrador,publicado,actualizar'],
        ]);
    }
}
