<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyerPersona;
use App\Models\Cliente;
use App\Support\Audiencia\GeneradorPersonas;
use App\Support\ClienteActivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

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

    /**
     * Cuestionario de 4 pasos: devuelve 3 borradores armados con una plantilla de
     * texto (ver GeneradorPersonas). NO guarda nada: el usuario carga el que quiera
     * en el formulario, lo edita y lo guarda con el store de siempre.
     */
    public function generar(Request $request, GeneradorPersonas $generador): JsonResponse
    {
        if (! ClienteActivo::modelo()) {
            return response()->json(['message' => 'Elige un cliente en el selector del menú antes de generar borradores.'], 422);
        }

        $limites = GeneradorPersonas::LIMITES;
        $data = $request->validate([
            'producto' => ['required', 'string', 'max:'.$limites['producto']],
            'publico' => ['required', 'string', 'max:'.$limites['publico']],
            'dolor' => ['required', 'string', 'max:'.$limites['dolor']],
            'donde' => ['required', 'string', 'max:'.$limites['donde']],
        ], [
            'producto.required' => 'Cuéntanos qué vende o hace la marca.',
            'publico.required' => 'Cuéntanos a quién le vende.',
            'dolor.required' => 'Escribe al menos un problema o dolor.',
            'donde.required' => 'Indica dónde se le encuentra.',
        ]);

        try {
            $personas = $generador->generar($data);
        } catch (InvalidArgumentException $e) {
            // Pasó «required» pero quedó vacío al limpiarlo (solo etiquetas, solo separadores…).
            return response()->json(['message' => 'Revisa las respuestas: alguna quedó vacía.', 'errors' => ['respuestas' => [$e->getMessage()]]], 422);
        }

        return response()->json(['personas' => $personas]);
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
