<?php

namespace App\Http\Controllers\Api\V1\Conversiones;

use App\Models\AdsConversionColumna;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;

class AdsConversionColumnaApiController
{
    public function index()
    {
        return Respuesta::coleccion(Serializador::coleccion(AdsConversionColumna::orderBy('nombre')->get()));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $columna = AdsConversionColumna::create($data);

        return Respuesta::recurso(Serializador::modelo($columna), 201);
    }

    public function update(Request $request, AdsConversionColumna $columna)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $columna->update($data);

        return Respuesta::recurso(Serializador::modelo($columna->fresh()));
    }

    public function destroy(AdsConversionColumna $columna)
    {
        $columna->delete();

        return Respuesta::eliminado();
    }
}
