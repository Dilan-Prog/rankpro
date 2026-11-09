<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AreaReporte;
use App\Enums\EstadoReporte;
use App\Enums\TipoSeccion;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Services\Reportes\CreadorReporte;
use App\Support\ClienteActivo;
use App\Support\Reportes\Plantillas;
use App\Support\Reportes\Precarga\AplicadorPrecarga;
use App\Support\Reportes\Precarga\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportesController extends Controller
{
    public function index(): View
    {
        // Cliente activo del menú: filtra la lista, los contadores y preselecciona «Nuevo reporte».
        $clienteActivo = ClienteActivo::modelo();

        $reportes = Reporte::with('cliente')
            ->withCount('entregas')
            ->when($clienteActivo, fn ($q) => $q->where('cliente_id', $clienteActivo->id))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Reporte $reporte) => $reporte->toRow());

        return view('admin.reportes.index', [
            'pageTitle' => 'Reportes',
            'reportes' => $reportes,
            'clientes' => Cliente::ordenadoPorNombreComercial()->get(['id', 'nombre', 'empresa']),
            'clienteActivo' => $clienteActivo,
            // Casillas «Añadir secciones avanzadas» del modal «Generar reporte del mes», por área.
            'avanzadasPorArea' => collect(AreaReporte::cases())->mapWithKeys(fn (AreaReporte $a) => [$a->value => Plantillas::avanzadas($a)])->all(),
            'mesActual' => now()->format('Y-m'),
            // Lo normal es reportar el mes que acaba de cerrar.
            'mesSugerido' => now()->startOfMonth()->subMonthNoOverflow()->format('Y-m'),
            'totalReportes' => $reportes->count(),
            'borradores' => $reportes->where('estado', EstadoReporte::Borrador->value)->count(),
            // `fecha_emision` se sella al pasar el reporte a Listo y ya no se
            // vuelve a mover: cualquier edición posterior (una nota, un cambio
            // de sección) tocaba `updated_at` y sacaba o metía el reporte del
            // recuento del mes según el día en que se le hubiera dado un
            // retoque.
            'entregadosMes' => Reporte::where('estado', EstadoReporte::Entregado->value)
                ->when($clienteActivo, fn ($q) => $q->where('cliente_id', $clienteActivo->id))
                ->whereBetween('fecha_emision', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ]);
    }

    public function __construct(private CreadorReporte $creador)
    {
    }

    /** "Reporte personalizado": periodo libre y plantilla clásica sin precarga de series mensuales. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, forUpdate: false, clienteId: $request->integer('cliente_id') ?: null);

        $reporte = $this->creador->crearPersonalizado($data, Auth::id());

        return response()->json([
            'show_url' => route('admin.reportes.show', $reporte),
            'reporte' => $reporte->fresh('cliente')->toRow(),
        ], 201);
    }

    /**
     * "Generar reporte del mes": plantilla (área) + un MES calendario completo. Crea el
     * borrador con título autogenerado, folio y secciones precargadas desde lo ya capturado.
     */
    public function generarMes(Request $request): JsonResponse
    {
        $clienteId = $request->integer('cliente_id') ?: null;

        $data = $request->validate([
            // `whereNull` porque `exists` no mira el borrado lógico: un cliente eliminado no debe recibir reportes.
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'area' => ['required', 'in:'.implode(',', array_column(AreaReporte::cases(), 'value'))],
            'mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'avanzadas' => ['nullable', 'array'],
            'avanzadas.*' => ['string'],
            'seo_campana_id' => ['nullable', 'integer', Rule::exists('seo_campanas', 'id')->where('cliente_id', $clienteId)],
            'ads_campana_id' => ['nullable', 'integer', Rule::exists('ads_campanas', 'id')->where('cliente_id', $clienteId)],
        ]);

        $area = AreaReporte::from($data['area']);
        $mes = Periodo::desdeTexto($data['mes']);

        // Un reporte mensual de un mes que no ha empezado solo tendría huecos.
        if ($mes->gt(now()->startOfMonth())) {
            throw ValidationException::withMessages(['mes' => 'Elige un mes que ya haya empezado.']);
        }

        $validas = array_keys(Plantillas::avanzadas($area));
        $avanzadas = array_values(array_unique((array) ($data['avanzadas'] ?? [])));
        if (array_diff($avanzadas, $validas) !== []) {
            throw ValidationException::withMessages(['avanzadas' => 'Alguna sección avanzada no existe para esta área.']);
        }

        // Doble clic o reintento: un mes, un reporte (por cliente y área). Si se quiere otro, se elimina el borrador.
        $existente = Reporte::where('cliente_id', $data['cliente_id'])
            ->where('area', $area->value)
            ->where('origen', 'mes')
            ->whereDate('periodo_inicio', $mes->toDateString())
            ->first();
        if ($existente) {
            return response()->json([
                'message' => 'Ya existe el reporte de '.$area->label().' de '.Periodo::etiqueta($mes).' para este cliente.',
                'errors' => ['mes' => ['Ya existe un reporte de ese mes para este cliente y área.']],
                'show_url' => route('admin.reportes.show', $existente),
            ], 422);
        }

        $reporte = $this->creador->crearDelMes(
            Cliente::findOrFail($data['cliente_id']),
            $area,
            $mes,
            $avanzadas,
            Auth::id(),
            $data['seo_campana_id'] ?? null,
            $data['ads_campana_id'] ?? null,
        );

        return response()->json([
            'show_url' => route('admin.reportes.show', $reporte),
            'reporte' => $reporte->fresh('cliente')->toRow(),
        ], 201);
    }

    /** "Actualizar datos": refresca solo lo precargado de un borrador del mes, sin pisar lo editado. */
    public function actualizarDatos(Reporte $reporte): JsonResponse
    {
        if (! $reporte->esDelMes()) {
            return response()->json(['message' => 'Solo los reportes generados con «Generar reporte del mes» tienen datos precargados.'], 422);
        }
        if ($reporte->estado !== EstadoReporte::Borrador) {
            return response()->json(['message' => 'Solo se pueden actualizar los datos de un borrador: un reporte listo o entregado ya se revisó.'], 422);
        }

        $resultado = (new AplicadorPrecarga)->actualizar($reporte);

        return response()->json($resultado + [
            'message' => sprintf(
                '%d actualizado%s, %d respetado%s por estar editado%s a mano y %d indicador%s sin dato del mes.',
                $resultado['actualizados'], $resultado['actualizados'] === 1 ? '' : 's',
                $resultado['conservados'], $resultado['conservados'] === 1 ? '' : 's', $resultado['conservados'] === 1 ? '' : 's',
                $resultado['sin_dato'], $resultado['sin_dato'] === 1 ? '' : 'es'
            ),
        ]);
    }

    public function show(Reporte $reporte): View
    {
        return view('admin.reportes.show', [
            'pageTitle' => $reporte->titulo,
            // `entregas.usuario` va en el load porque la tabla del histórico
            // pinta quién generó cada entrega: sin precargarlo son N consultas.
            'reporte' => $reporte->load('cliente', 'secciones', 'entregas.archivo', 'entregas.usuario', 'creador'),
            'enlaces' => $reporte->enlaces()->limit(10)->get()->map->toRow()->all(),
            // Menú "añadir sección": los tipos que admiten pegado se marcan
            // aquí para que la vista no tenga que conocer el enum.
            'tiposSeccion' => collect(TipoSeccion::cases())->map(fn (TipoSeccion $tipo) => [
                'value' => $tipo->value,
                'label' => $tipo->label(),
                'admite_pegado' => $tipo->admitePegado(),
            ])->values(),
        ]);
    }

    public function update(Request $request, Reporte $reporte): JsonResponse
    {
        $data = $this->validated($request, forUpdate: true, clienteId: $reporte->cliente_id);

        // Entregado a mano sin fecha_emision no cuenta en `entregadosMes` (se mide
        // por fecha_emision): se estampa hoy, sin pisar una fecha ya existente.
        if ($data['estado'] === EstadoReporte::Entregado->value && $reporte->fecha_emision === null) {
            $data['fecha_emision'] = now();
        }

        $reporte->update($data);

        return response()->json($reporte->fresh('cliente')->loadCount('entregas')->toRow());
    }

    public function destroy(Reporte $reporte): JsonResponse
    {
        $reporte->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * `cliente_id` y `area` son inmutables: el área decide la plantilla de
     * secciones que ya se sembró, y cambiarla dejaría el reporte con secciones
     * que no le corresponden.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $forUpdate, ?int $clienteId = null): array
    {
        $reglas = [
            'titulo' => ['required', 'string', 'max:255'],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fin' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'notas_alcance' => ['nullable', 'string', 'max:5000'],
            // La campaña debe ser del cliente del reporte: si no, un reporte de A
            // jalaria metricas de la campaña de B (Propuestas ya lo valida igual).
            'seo_campana_id' => ['nullable', 'integer', Rule::exists('seo_campanas', 'id')->where('cliente_id', $clienteId)],
            'ads_campana_id' => ['nullable', 'integer', Rule::exists('ads_campanas', 'id')->where('cliente_id', $clienteId)],
        ];

        $reglas += $forUpdate
            ? ['estado' => ['required', 'in:'.implode(',', array_column(EstadoReporte::cases(), 'value'))]]
            : [
                'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
                'area' => ['required', 'in:'.implode(',', array_column(AreaReporte::cases(), 'value'))],
            ];

        return $request->validate($reglas);
    }
}
