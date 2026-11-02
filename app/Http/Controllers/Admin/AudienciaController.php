<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyerPersona;
use App\Models\Cliente;
use App\Support\ClienteActivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Buyer personas del cliente activo del menú: captura manual con tarjetas y
 * modal. Todo opera sobre el cliente activo de la sesión, nunca sobre un id
 * enviado por el formulario, para que una persona de otro cliente no se pueda tocar.
 */
class AudienciaController extends Controller
{
    public function index(): View
    {
        $cliente = ClienteActivo::modelo();

        return view('admin.audiencia.index', [
            'pageTitle' => 'Audiencia',
            'cliente' => $cliente,
            'personas' => $cliente ? $cliente->buyerPersonas : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cliente = ClienteActivo::modelo();
        if (! $cliente) {
            return $this->sinCliente();
        }

        $data = $this->validar($request);
        $data['orden'] = ((int) $cliente->buyerPersonas()->max('orden')) + 1;
        $cliente->buyerPersonas()->create($data);

        return redirect()->route('admin.audiencia.index')->with('status', 'Buyer persona creada.');
    }

    public function update(Request $request, BuyerPersona $persona): RedirectResponse
    {
        $this->autorizarPersona($persona);
        $persona->update($this->validar($request));

        return redirect()->route('admin.audiencia.index')->with('status', 'Buyer persona actualizada.');
    }

    public function destroy(BuyerPersona $persona): RedirectResponse
    {
        $this->autorizarPersona($persona);
        $persona->delete();

        return redirect()->route('admin.audiencia.index')->with('status', 'Buyer persona eliminada.');
    }

    /** Sube o baja una posición intercambiando con la vecina y renumerando todo de 0 a n. */
    public function mover(Request $request, BuyerPersona $persona): RedirectResponse
    {
        $this->autorizarPersona($persona);
        $data = $request->validate(['direccion' => ['required', 'in:arriba,abajo']]);

        DB::transaction(function () use ($persona, $data) {
            $ids = BuyerPersona::query()
                ->where('cliente_id', $persona->cliente_id)
                ->orderBy('orden')->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')->all();

            $i = array_search($persona->id, $ids, true);
            $j = $data['direccion'] === 'arriba' ? $i - 1 : $i + 1;

            if ($i !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            }

            foreach ($ids as $posicion => $id) {
                BuyerPersona::query()->whereKey($id)->update(['orden' => $posicion]);
            }
        });

        return redirect()->route('admin.audiencia.index');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'perfil' => ['nullable', 'string', 'max:2000'],
            'dolores' => ['nullable', 'string', 'max:2000'],
            'mensaje' => ['nullable', 'string', 'max:2000'],
            'canales' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** Una persona solo se toca desde el cliente al que pertenece; si no, 404 (no se revela que existe). */
    private function autorizarPersona(BuyerPersona $persona): void
    {
        abort_unless(ClienteActivo::id() !== null && $persona->cliente_id === ClienteActivo::id(), 404);
    }

    private function sinCliente(): RedirectResponse
    {
        return redirect()->route('admin.audiencia.index')->withErrors(['cliente' => 'Elige un cliente en el selector del menú antes de guardar.']);
    }
}
