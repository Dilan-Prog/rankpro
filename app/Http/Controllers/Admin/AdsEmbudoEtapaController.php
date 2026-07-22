<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsEmbudoEtapa;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdsEmbudoEtapaController extends Controller
{
    public function store(Request $request, Cliente $cliente): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $siguienteOrden = ((int) $cliente->embudoEtapas()->max('orden')) + 1;

        $cliente->embudoEtapas()->create([
            'nombre' => $data['nombre'],
            'orden' => $siguienteOrden,
        ]);

        return back()->with('status', "Etapa \"{$data['nombre']}\" agregada al embudo.");
    }

    public function update(Request $request, AdsEmbudoEtapa $etapa): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $etapa->update($data);

        return back()->with('status', 'Etapa actualizada.');
    }

    public function destroy(AdsEmbudoEtapa $etapa): RedirectResponse
    {
        $etapa->delete();

        return back()->with('status', 'Etapa eliminada. Las conversiones que estaban ahí quedan sin clasificar.');
    }
}
