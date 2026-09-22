<?php

namespace App\Http\Controllers\Api\V1\Conversiones;

use App\Models\AdsEmbudoEtapa;
use App\Models\Cliente;
use App\Support\Api\{Respuesta, Serializador};
use Illuminate\Http\Request;

class AdsEmbudoEtapaApiController
{
    public function index(Cliente $cliente)
    {
        return Respuesta::coleccion(Serializador::coleccion($cliente->embudoEtapas()->orderBy('orden')->get()));
    }

    public function store(Request $request, Cliente $cliente)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $siguienteOrden = ((int) $cliente->embudoEtapas()->max('orden')) + 1;

        $etapa = $cliente->embudoEtapas()->create([
            'nombre' => $data['nombre'],
            'orden' => $siguienteOrden,
        ]);

        return Respuesta::recurso(Serializador::modelo($etapa), 201);
    }

    public function update(Request $request, AdsEmbudoEtapa $etapa)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $etapa->update($data);

        return Respuesta::recurso(Serializador::modelo($etapa->fresh()));
    }

    public function destroy(AdsEmbudoEtapa $etapa)
    {
        $etapa->delete();

        return Respuesta::eliminado();
    }
}
