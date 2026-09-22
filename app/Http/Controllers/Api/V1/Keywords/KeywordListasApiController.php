<?php

namespace App\Http\Controllers\Api\V1\Keywords;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\KeywordLista;
use App\Support\Api\{ConsultaOpciones, Respuesta};
use App\Support\Reglas\KeywordListas as ReglasKeywordListas;
use Illuminate\Http\Request;

class KeywordListasApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(KeywordLista::query(), $request, new ConsultaOpciones(
            buscarEn: ['nombre', 'canal'],
            filtrosExactos: ['cliente_id', 'responsable_id', 'estado'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
            incluibles: ['cliente', 'responsable', 'keywords'],
        ));
    }

    public function show(KeywordLista $lista)
    {
        return Respuesta::recurso($lista->load('cliente', 'responsable', 'keywords')->toRow());
    }

    public function store(Request $request)
    {
        $lista = KeywordLista::create($request->validate(ReglasKeywordListas::guardar()));

        return Respuesta::recurso($lista->fresh(['cliente', 'responsable', 'keywords'])->toRow(), 201);
    }

    public function update(Request $request, KeywordLista $lista)
    {
        $lista->update($request->validate(ReglasKeywordListas::guardar()));

        return Respuesta::recurso($lista->fresh(['cliente', 'responsable', 'keywords'])->toRow());
    }

    public function destroy(KeywordLista $lista)
    {
        $lista->delete();

        return Respuesta::eliminado();
    }
}
