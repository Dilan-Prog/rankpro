<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoConversion;
use App\Http\Controllers\Controller;
use App\Models\AdsConversion;
use App\Models\Cliente;
use App\Support\Labels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $query = $this->conversionesFiltradas($request);

        $clienteSeleccionado = $request->filled('cliente_id')
            ? Cliente::find($request->get('cliente_id'))
            : null;

        return view('admin.conversiones.index', [
            'pageTitle' => 'Conversiones',
            'conversiones' => $query->with('cliente.embudoEtapas', 'adsClic.adsCampana', 'etapa')->latest('created_at')->paginate(50)->withQueryString(),
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre']),
            'clienteSeleccionado' => $clienteSeleccionado,
            'etapas' => $clienteSeleccionado?->embudoEtapas ?? collect(),
            'tiposConversion' => TipoConversion::cases(),
        ]);
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

    public function exportarExcel(Request $request)
    {
        $conversiones = $this->conversionesFiltradas($request)
            ->with('cliente', 'adsClic.adsCampana', 'etapa')
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
                    $conversion->cliente->nombre,
                    Labels::tipoConversion($conversion->tipo->value),
                    $conversion->etapa->nombre ?? 'Sin clasificar',
                    $conversion->adsClic?->adsCampana?->nombre ?? '—',
                    $conversion->valor,
                    $conversion->moneda,
                    $conversion->estado->value,
                    $conversion->gclid ?? $conversion->gbraid ?? $conversion->wbraid,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function conversionesFiltradas(Request $request)
    {
        $query = AdsConversion::query();

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
