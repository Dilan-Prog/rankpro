<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoConversion;
use App\Http\Controllers\Controller;
use App\Models\AdsConversion;
use App\Models\AdsConversionColumna;
use App\Models\AdsEmbudoEtapa;
use App\Models\Cliente;
use App\Support\ClienteActivo;
use App\Support\Conversiones\ResumenExportacion;
use App\Support\Labels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Vista global (cruza clientes) de conversiones — complementa, no reemplaza,
 * la vista por cliente en IntegracionesController::conversiones(). La
 * gestión de las etapas del embudo (crear/renombrar/borrar) se queda en la
 * página por cliente, ya que las etapas son propias de cada cliente; aquí
 * solo se filtra, clasifica y exporta.
 */
class ConversionesController extends Controller
{
    public function index(Request $request): View
    {
        // Cliente activo del menú: se aplica antes que el resto de filtros (un cliente_id explícito manda).
        $clienteActivo = ClienteActivo::aplicarA($request);

        // Las repetidas (doble clic) salen de la lista salvo que se pidan con ?duplicadas=1.
        $verDuplicadas = $request->boolean('duplicadas');
        $query = $this->conversionesFiltradas($request, $verDuplicadas ? 'solo' : 'excluir');
        $resumen = $this->resumen($request);

        $clienteSeleccionado = $request->filled('cliente_id')
            ? Cliente::find($request->get('cliente_id'))
            : null;

        return view('admin.conversiones.index', [
            'pageTitle' => 'Conversiones',
            'clienteActivo' => $clienteActivo,
            'verDuplicadas' => $verDuplicadas,
            'resumen' => $resumen,
            'conversiones' => $query->with('cliente.embudoEtapas', 'adsClic.adsCampana', 'campana', 'etapa', 'duplicadaDe')->latest('created_at')->paginate(50)->withQueryString(),
            'clientes' => Cliente::ordenadoPorNombreComercial()->get(['id', 'nombre', 'empresa']),
            'clienteSeleccionado' => $clienteSeleccionado,
            'etapas' => $clienteSeleccionado?->embudoEtapas ?? collect(),
            'tiposConversion' => TipoConversion::cases(),
            'columnas' => AdsConversionColumna::orderBy('nombre')->get(),
        ]);
    }

    /**
     * Edición en línea (AJAX) del valor y de las columnas personalizadas —
     * el resto de los campos del registro (fecha, cliente, tipo,
     * identificador) se quedan bloqueados a propósito, ya que vienen del
     * script de tracking y no deben poder desincronizarse de la fuente real.
     */
    public function actualizar(Request $request, AdsConversion $conversion): JsonResponse
    {
        $data = $request->validate([
            'valor' => ['nullable', 'numeric', 'min:0'],
            'datos_personalizados' => ['nullable', 'array'],
            'datos_personalizados.*' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->has('valor')) {
            $conversion->valor = $data['valor'] ?? null;
        }

        if ($request->has('datos_personalizados')) {
            // array_replace (no array_merge) — preserva las claves numéricas
            // (IDs de columna) tal cual, sin reindexarlas como lista.
            $conversion->datos_personalizados = array_replace(
                $conversion->datos_personalizados ?? [],
                $data['datos_personalizados']
            );
        }

        $conversion->save();

        return response()->json($conversion->fresh());
    }

    public function asignarEtapa(Request $request, AdsConversion $conversion): RedirectResponse
    {
        $data = $request->validate([
            'ads_embudo_etapa_id' => ['nullable', 'exists:ads_embudo_etapas,id'],
        ]);

        // La etapa elegida debe pertenecer al mismo cliente de la conversión — evita mezclar etapas entre clientes distintos.
        if (! empty($data['ads_embudo_etapa_id'])) {
            $perteneceAlCliente = $conversion->cliente->embudoEtapas()->where('id', $data['ads_embudo_etapa_id'])->exists();
            abort_unless($perteneceAlCliente, 422, 'La etapa seleccionada no pertenece a este cliente.');
        }

        $conversion->update(['ads_embudo_etapa_id' => $data['ads_embudo_etapa_id'] ?? null]);

        return back()->with('status', 'Conversión clasificada correctamente.');
    }

    /**
     * Clasifica varias conversiones en una etapa (o las deja sin clasificar con quitar_etapa).
     * Todas deben ser del mismo cliente y la etapa de ese cliente: las etapas son por cliente,
     * mezclarlas dejaría conversiones en el embudo equivocado.
     */
    public function asignarEtapaLote(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'conversion_ids' => ['required', 'array', 'min:1', 'max:500'],
            'conversion_ids.*' => ['integer', 'distinct'],
            // Etapa vacía no significa «quitar»: un envío sin elegir etapa no debe borrar clasificaciones por accidente.
            'ads_embudo_etapa_id' => ['nullable', 'required_without:quitar_etapa', 'integer', 'exists:ads_embudo_etapas,id'],
            'quitar_etapa' => ['sometimes', 'boolean'],
        ], [
            'conversion_ids.required' => 'Selecciona al menos una conversión.',
            'conversion_ids.min' => 'Selecciona al menos una conversión.',
            'ads_embudo_etapa_id.required_without' => 'Elige una etapa (o usa «Quitar etapa»).',
        ]);

        $conversiones = $this->cargarSeleccion($data['conversion_ids']);
        $clienteId = $this->clienteUnico($conversiones);

        $etapaId = $request->boolean('quitar_etapa') ? null : ($data['ads_embudo_etapa_id'] ?? null);
        $etapa = $etapaId !== null ? AdsEmbudoEtapa::find($etapaId) : null;
        if ($etapa && $etapa->cliente_id !== $clienteId) {
            throw ValidationException::withMessages(['ads_embudo_etapa_id' => 'La etapa seleccionada no pertenece al cliente de las conversiones.']);
        }

        $actualizadas = AdsConversion::whereIn('id', $conversiones->pluck('id'))->update(['ads_embudo_etapa_id' => $etapaId]);

        $mensaje = $etapa
            ? "{$actualizadas} conversión(es) clasificadas como «{$etapa->nombre}»."
            : "{$actualizadas} conversión(es) quedaron sin clasificar.";

        return $request->wantsJson()
            ? response()->json(['actualizadas' => $actualizadas, 'mensaje' => $mensaje])
            : back()->with('status', $mensaje);
    }

    /**
     * Marca a mano varias conversiones como repetidas: se conserva la más antigua de la selección.
     * Sirve para lo que la ventana automática no alcanza (p. ej. el mismo prospecto desde dos dispositivos).
     */
    public function marcarDuplicadas(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'conversion_ids' => ['required', 'array', 'min:2', 'max:500'],
            'conversion_ids.*' => ['integer', 'distinct'],
        ], [
            'conversion_ids.required' => 'Selecciona al menos dos conversiones: una se conserva y las demás se marcan.',
            'conversion_ids.min' => 'Selecciona al menos dos conversiones: una se conserva y las demás se marcan.',
        ]);

        $conversiones = $this->cargarSeleccion($data['conversion_ids']);
        $this->clienteUnico($conversiones);

        if ($conversiones->contains(fn ($c) => $c->esDuplicada())) {
            throw ValidationException::withMessages(['conversion_ids' => 'La selección incluye conversiones que ya están marcadas como duplicadas.']);
        }

        $original = $conversiones->sortBy([['created_at', 'asc'], ['id', 'asc']])->first();
        $marcadas = AdsConversion::whereIn('id', $conversiones->pluck('id')->reject(fn ($id) => $id === $original->id))
            ->whereNull('duplicada_de_id')
            ->update(['duplicada_de_id' => $original->id, 'duplicada_at' => now()]);

        $mensaje = "{$marcadas} conversión(es) marcadas como duplicadas de la #{$original->id}.";

        return $request->wantsJson()
            ? response()->json(['marcadas' => $marcadas, 'original_id' => $original->id, 'mensaje' => $mensaje])
            : back()->with('status', $mensaje);
    }

    public function desmarcarDuplicada(Request $request, AdsConversion $conversion): RedirectResponse|JsonResponse
    {
        abort_unless($conversion->esDuplicada(), 422, 'La conversión no está marcada como duplicada.');

        $conversion->update(['duplicada_de_id' => null, 'duplicada_at' => null]);

        $mensaje = "La conversión #{$conversion->id} volvió a la lista.";

        return $request->wantsJson()
            ? response()->json(['mensaje' => $mensaje])
            : back()->with('status', $mensaje);
    }

    /** @param  array<int, int|string>  $ids */
    private function cargarSeleccion(array $ids)
    {
        $conversiones = AdsConversion::whereIn('id', $ids)->get();

        if ($conversiones->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['conversion_ids' => 'Alguna de las conversiones seleccionadas ya no existe.']);
        }

        return $conversiones;
    }

    private function clienteUnico($conversiones): int
    {
        $clientes = $conversiones->pluck('cliente_id')->unique();

        if ($clientes->count() > 1) {
            throw ValidationException::withMessages(['conversion_ids' => 'Las conversiones seleccionadas son de varios clientes: filtra por un cliente y vuelve a seleccionar.']);
        }

        return (int) $clientes->first();
    }

    /**
     * Cifras de la lista filtrada (sin repetidas) para que la pantalla diga qué se podría subir a Google Ads
     * y qué no, en vez de dejar 100 filas «pendientes» que parecen todas iguales.
     *
     * @return array{total: int, duplicadas: int, sin_identificador: int, sin_etapa: int, exportables: int}
     */
    private function resumen(Request $request): array
    {
        return ResumenExportacion::calcular(
            $this->conversionesFiltradas($request, 'excluir'),
            $this->conversionesFiltradas($request, 'solo')->count()
        );
    }

    public function exportarExcel(Request $request)
    {
        $conversiones = $this->conversionesFiltradas($request)
            ->with('cliente', 'adsClic.adsCampana', 'campana', 'etapa')
            ->orderBy('created_at')
            ->get();

        $filename = 'conversiones-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($conversiones) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, ['Fecha', 'Cliente', 'Tipo', 'Etapa del embudo', 'Campaña', 'Valor', 'Moneda', 'Estado', 'Identificador (gclid/gbraid/wbraid)']);

            foreach ($conversiones as $conversion) {
                fputcsv($out, [
                    $conversion->created_at->format('Y-m-d H:i'),
                    $conversion->cliente->nombre_comercial,
                    Labels::tipoConversion($conversion->tipo->value),
                    $conversion->etapa->nombre ?? 'Sin clasificar',
                    $conversion->campanaNombre() ?? '—',
                    $conversion->valor,
                    $conversion->moneda,
                    $conversion->estado->value,
                    $conversion->gclid ?? $conversion->gbraid ?? $conversion->wbraid,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @param  'excluir'|'solo'|'todas'  $duplicadas  las repetidas no deben colarse en listas ni exportes */
    private function conversionesFiltradas(Request $request, string $duplicadas = 'excluir')
    {
        $query = AdsConversion::query();

        match ($duplicadas) {
            'excluir' => $query->whereNull('duplicada_de_id'),
            'solo' => $query->whereNotNull('duplicada_de_id'),
            default => null,
        };

        // La exportación comparte este filtro: lo que se descarga es lo que se ve.
        ClienteActivo::aplicarA($request);

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->get('cliente_id'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->get('fecha_hasta'));
        }

        if ($request->filled('tipo')) {
            $query->whereIn('tipo', (array) $request->get('tipo'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }

        if ($request->filled('valor_min')) {
            $query->where('valor', '>=', $request->get('valor_min'));
        }

        // El filtro de etapa solo aplica de forma fiable cuando ya se eligió un cliente (las etapas son por cliente, no globales).
        if ($request->filled('ads_embudo_etapa_id') && $request->filled('cliente_id')) {
            $etapaId = $request->get('ads_embudo_etapa_id');
            $etapaId === 'sin_clasificar'
                ? $query->whereNull('ads_embudo_etapa_id')
                : $query->where('ads_embudo_etapa_id', $etapaId);
        }

        return $query;
    }
}
