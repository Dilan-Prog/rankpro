<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoPropuesta;
use App\Enums\TipoArchivo;
use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Propuesta;
use App\Models\SeoCampana;
use App\Support\Propuestas\Calculos;
use App\Support\Propuestas\Plantilla;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropuestaController extends Controller
{
    public function index(Request $request): View
    {
        $query = Propuesta::with('cliente')->orderByDesc('updated_at');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('search')) {
            $buscar = trim((string) $request->string('search'));
            $query->where(function ($q) use ($buscar) {
                $q->where('folio', 'like', "%{$buscar}%")
                    ->orWhere('titulo', 'like', "%{$buscar}%")
                    ->orWhereHas('cliente', function ($c) use ($buscar) {
                        $c->where('nombre', 'like', "%{$buscar}%")->orWhere('empresa', 'like', "%{$buscar}%");
                    });
            });
        }

        $propuestas = $query->get();

        $aprobadas = $propuestas->where('estado', EstadoPropuesta::Aprobada)->count();
        $rechazadas = $propuestas->where('estado', EstadoPropuesta::Rechazada)->count();
        $decididas = $aprobadas + $rechazadas;

        // El picker de campaña real del modal "Nueva Propuesta" viaja precargado
        // como mapa cliente_id -> campañas (mismo patrón que el banco de
        // keywords de seo/show.blade.php: un solo <script type="application/json">
        // parseado una vez en JS, sin ida y vuelta al servidor por cliente).
        $campanasPorCliente = SeoCampana::orderBy('nombre')
            ->get(['id', 'cliente_id', 'nombre'])
            ->groupBy('cliente_id');

        return view('admin.propuestas.index', [
            'pageTitle' => 'Propuestas de Continuidad',
            'propuestas' => $propuestas->map(fn (Propuesta $p) => $p->toRow())->values(),
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa']),
            'campanasPorCliente' => $campanasPorCliente,
            'kpis' => [
                'total' => $propuestas->count(),
                'borrador' => $propuestas->where('estado', EstadoPropuesta::Borrador)->count(),
                'enviada' => $propuestas->where('estado', EstadoPropuesta::Enviada)->count(),
                'tasa_aprobacion' => $decididas > 0 ? round($aprobadas / $decididas * 100) : null,
                'aprobadas' => $aprobadas,
                'decididas' => $decididas,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'seo_campana_id' => [
                'nullable', 'integer',
                Rule::exists('seo_campanas', 'id')->where('cliente_id', $request->integer('cliente_id')),
            ],
            'titulo' => ['nullable', 'string', 'max:255'],
        ]);

        $propuesta = Propuesta::create(Plantilla::vacio() + [
            'cliente_id' => $data['cliente_id'],
            'seo_campana_id' => $data['seo_campana_id'] ?? null,
            'titulo' => $data['titulo'] ?? 'Propuesta de Continuidad SEO',
            'estado' => EstadoPropuesta::Borrador->value,
            'creado_por' => Auth::id(),
        ]);

        return response()->json([
            'show_url' => route('admin.propuestas.show', $propuesta),
            'propuesta' => $propuesta->fresh('cliente')->toRow(),
        ], 201);
    }

    public function show(Propuesta $propuesta): View
    {
        return view('admin.propuestas.show', [
            'pageTitle' => $propuesta->titulo,
            'propuesta' => $propuesta->load('cliente', 'seoCampana'),
            'tieneCampana' => $propuesta->seo_campana_id !== null,
        ]);
    }

    /** Solo `estado`: cliente_id/seo_campana_id son inmutables tras crear (ver Plan). */
    public function update(Request $request, Propuesta $propuesta): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(array_column(EstadoPropuesta::cases(), 'value'))],
        ]);

        $propuesta->update($data);

        return response()->json($propuesta->fresh('cliente')->toRow());
    }

    public function destroy(Propuesta $propuesta): JsonResponse
    {
        $propuesta->delete();

        return response()->json(['deleted' => true]);
    }

    public function actualizarSeccion(Request $request, Propuesta $propuesta, string $seccion): JsonResponse
    {
        return match ($seccion) {
            'resumen' => $this->guardarResumen($request, $propuesta),
            'situacion' => $this->guardarColumna($request, $propuesta, 'situacion_actual', [
                'kpis' => ['required', 'array', 'size:5'],
                'kpis.*.etiqueta' => ['nullable', 'string', 'max:80'],
                'kpis.*.valor' => ['nullable', 'string', 'max:40'],
                'kpis.*.color' => ['nullable', 'string', 'max:20'],
                'periodo_comparacion' => ['nullable', 'array'],
                'periodo_comparacion.label_1' => ['nullable', 'string', 'max:40'],
                'periodo_comparacion.label_2' => ['nullable', 'string', 'max:40'],
                'tabla_comparacion' => ['nullable', 'array'],
                'tabla_comparacion.*.metrica' => ['nullable', 'string', 'max:80'],
                'tabla_comparacion.*.valor_1' => ['nullable', 'string', 'max:40'],
                'tabla_comparacion.*.valor_2' => ['nullable', 'string', 'max:40'],
                'tabla_consultas' => ['required', 'array', 'min:1'],
                'tabla_consultas.*.consulta' => ['nullable', 'string', 'max:255'],
                'tabla_consultas.*.posicion' => ['nullable', 'string', 'max:20'],
                'tabla_consultas.*.impresiones' => ['nullable', 'string', 'max:20'],
                'tabla_consultas.*.clics' => ['nullable', 'string', 'max:20'],
                'tabla_consultas.*.oportunidad' => ['nullable', 'string', 'max:120'],
                'resumen_texto' => ['nullable', 'string', 'max:3000'],
                'insight_texto' => ['nullable', 'string', 'max:3000'],
            ]),
            'contexto' => $this->guardarColumna($request, $propuesta, 'contexto_continuidad', [
                'tabla_riesgos' => ['required', 'array', 'min:1'],
                'tabla_riesgos.*.riesgo' => ['nullable', 'string', 'max:255'],
                'tabla_riesgos.*.impacto' => ['nullable', 'string', 'max:500'],
                'checklist_protege' => ['required', 'array', 'min:1'],
                'checklist_protege.*.titulo' => ['nullable', 'string', 'max:120'],
                'checklist_protege.*.descripcion' => ['nullable', 'string', 'max:500'],
                'intro_texto' => ['nullable', 'string', 'max:3000'],
                'logica_negocio_texto' => ['nullable', 'string', 'max:3000'],
            ]),
            'plan' => $this->guardarColumna($request, $propuesta, 'plan_detalle', [
                'nombre' => ['nullable', 'string', 'max:120'],
                'duracion_meses' => ['nullable', 'string', 'max:20'],
                'vigencia_inicio_texto' => ['nullable', 'string', 'max:80'],
                'vigencia_fin_texto' => ['nullable', 'string', 'max:80'],
                'alcance_incluido' => ['required', 'array', 'min:1'],
                'alcance_incluido.*' => ['nullable', 'string', 'max:255'],
                'alcance_no_incluido' => ['required', 'array', 'min:1'],
                'alcance_no_incluido.*' => ['nullable', 'string', 'max:255'],
                'meses' => ['required', 'array', 'min:1'],
                'meses.*.mes_label' => ['nullable', 'string', 'max:20'],
                'meses.*.calendario' => ['nullable', 'string', 'max:80'],
                'meses.*.actividades' => ['nullable', 'string', 'max:1000'],
                'meses.*.entregable' => ['nullable', 'string', 'max:255'],
                'meses.*.horas' => ['nullable', 'string', 'max:20'],
            ]),
            'condiciones' => $this->guardarColumna($request, $propuesta, 'condiciones_proyeccion', [
                'condiciones' => ['required', 'array', 'min:1'],
                'condiciones.*.etiqueta' => ['nullable', 'string', 'max:80'],
                'condiciones.*.valor' => ['nullable', 'string', 'max:500'],
                'opciones_renegociacion' => ['required', 'array', 'size:3'],
                'opciones_renegociacion.*.nombre' => ['nullable', 'string', 'max:120'],
                'opciones_renegociacion.*.descripcion' => ['nullable', 'string', 'max:500'],
                'proyeccion_periodo_label' => ['nullable', 'string', 'max:60'],
                'tabla_proyeccion' => ['required', 'array', 'min:1'],
                'tabla_proyeccion.*.metrica' => ['nullable', 'string', 'max:80'],
                'tabla_proyeccion.*.base' => ['nullable', 'string', 'max:40'],
                'tabla_proyeccion.*.proyeccion' => ['nullable', 'string', 'max:40'],
                'tabla_proyeccion.*.escenario' => ['nullable', 'string', 'max:60'],
            ]),
            default => abort(404),
        };
    }

    /**
     * Caso especial: el payload de la Pestaña 1 trae también titulo/precio_mensual/
     * horas_mensuales/tarifa_hora, que viven en columnas reales (se usan en el
     * índice), no en el JSON `resumen` — se separan en un solo update() de Eloquent.
     */
    private function guardarResumen(Request $request, Propuesta $propuesta): JsonResponse
    {
        $data = $request->validate([
            'titulo' => ['nullable', 'string', 'max:255'],
            'precio_mensual' => ['nullable', 'numeric', 'min:0'],
            'horas_mensuales' => ['nullable', 'integer', 'min:0'],
            'tarifa_hora' => ['nullable', 'numeric', 'min:0'],
            'sitio_web' => ['nullable', 'string', 'max:255'],
            'subtitulo_plan' => ['nullable', 'string', 'max:120'],
            'vigencia_label' => ['nullable', 'string', 'max:80'],
            'estadisticas_destacadas' => ['required', 'array', 'min:2', 'max:4'],
            'estadisticas_destacadas.*.etiqueta' => ['nullable', 'string', 'max:60'],
            'estadisticas_destacadas.*.valor' => ['nullable', 'string', 'max:40'],
            'estadisticas_destacadas.*.nota' => ['nullable', 'string', 'max:60'],
        ]);

        $propuesta->update([
            'titulo' => $data['titulo'] ?? $propuesta->titulo,
            'precio_mensual' => $data['precio_mensual'] ?? null,
            'horas_mensuales' => $data['horas_mensuales'] ?? null,
            'tarifa_hora' => $data['tarifa_hora'] ?? null,
            'resumen' => [
                'sitio_web' => $data['sitio_web'] ?? '',
                'subtitulo_plan' => $data['subtitulo_plan'] ?? '',
                'vigencia_label' => $data['vigencia_label'] ?? '',
                'estadisticas_destacadas' => $data['estadisticas_destacadas'],
            ],
        ]);

        return response()->json($propuesta->fresh()->only(['titulo', 'precio_mensual', 'horas_mensuales', 'tarifa_hora', 'resumen']));
    }

    private function guardarColumna(Request $request, Propuesta $propuesta, string $columna, array $reglas): JsonResponse
    {
        $data = $request->validate($reglas);

        $propuesta->update([$columna => $data]);

        return response()->json($propuesta->fresh()->{$columna});
    }

    /** Trae keywords reales del banco del cliente vinculado — no reemplaza filas ya capturadas a mano. */
    public function sugerirConsultas(Propuesta $propuesta): JsonResponse
    {
        if ($propuesta->seo_campana_id === null) {
            return response()->json(['message' => 'Esta propuesta no tiene una campaña SEO vinculada.'], 422);
        }

        $situacion = $propuesta->situacion_actual ?? [];
        $existentes = $situacion['tabla_consultas'] ?? [];
        $sugeridas = $propuesta->sugerirConsultasDesdeBanco();

        if ($sugeridas === []) {
            return response()->json(['message' => 'El banco de keywords de este cliente está vacío.'], 422);
        }

        $situacion['tabla_consultas'] = array_merge($existentes, $sugeridas);
        $propuesta->update(['situacion_actual' => $situacion]);

        return response()->json([
            'agregadas' => count($sugeridas),
            'situacion_actual' => $propuesta->fresh()->situacion_actual,
        ]);
    }

    public function preview(Propuesta $propuesta): View
    {
        $html = view('pdf.propuesta-continuidad', $this->datosDocumento($propuesta, 'VISTA PREVIA'))->render();

        return view('admin.archivos.documento-preview', [
            'pageTitle' => 'Vista previa — '.$propuesta->titulo,
            'documentoHtml' => $html,
            'formAction' => route('admin.propuestas.pdf', $propuesta),
            'cancelRoute' => route('admin.propuestas.show', $propuesta),
            'hidden' => [],
        ]);
    }

    public function generarPdf(Propuesta $propuesta): StreamedResponse
    {
        // Idempotente: folio/fecha_emision se asignan una sola vez, descargas
        // posteriores mantienen el mismo folio (ver Plan, precedente de Reporte::numero).
        if ($propuesta->folio === null) {
            $numero = 'PROP-CONT-'.now()->format('Y').'-'.str_pad((string) (Propuesta::whereNotNull('folio')->count() + 1), 4, '0', STR_PAD_LEFT);
            $propuesta->update(['folio' => $numero, 'fecha_emision' => now()]);
        }

        $pdf = Pdf::loadView('pdf.propuesta-continuidad', $this->datosDocumento($propuesta, $propuesta->folio))
            ->setPaper('letter');

        $filename = "{$propuesta->folio}.pdf";
        $path = "clientes/{$propuesta->cliente_id}/propuestas-continuidad/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        Archivo::create([
            'cliente_id' => $propuesta->cliente_id,
            'nombre' => "Propuesta de Continuidad {$propuesta->folio} — {$propuesta->cliente->nombre}.pdf",
            'tipo' => TipoArchivo::Propuesta->value,
            'ruta_archivo' => $path,
            'tamano' => Storage::disk('local')->size($path),
            'extension' => 'pdf',
            'subido_por' => Auth::id(),
        ]);

        return Storage::disk('local')->download($path, $filename);
    }

    /**
     * Todos los cálculos derivados (variación %, subtotal, textos compuestos)
     * se resuelven aquí, nunca dentro del Blade — así se pueden testear aparte.
     *
     * @return array<string, mixed>
     */
    private function datosDocumento(Propuesta $propuesta, string $folioMostrado): array
    {
        $propuesta->loadMissing('cliente');

        $resumen = $propuesta->resumen ?? [];
        $situacion = $propuesta->situacion_actual ?? [];
        $contexto = $propuesta->contexto_continuidad ?? [];
        $plan = $propuesta->plan_detalle ?? [];
        $condiciones = $propuesta->condiciones_proyeccion ?? [];

        $nombreCliente = $propuesta->cliente->empresa ?: $propuesta->cliente->nombre;
        $partesNombre = explode(' ', trim($nombreCliente), 2);

        $tablaComparacion = collect($situacion['tabla_comparacion'] ?? [])->map(function (array $fila) {
            $variacion = Calculos::variacion($fila['valor_1'] ?? null, $fila['valor_2'] ?? null);

            return $fila + $variacion;
        })->all();

        return [
            'folio' => $folioMostrado,
            'cliente' => $propuesta->cliente,
            'clienteNombre1' => $partesNombre[0] ?? $nombreCliente,
            'clienteNombre2' => $partesNombre[1] ?? '',
            'titulo' => $propuesta->titulo,
            'estado' => $propuesta->estado,
            'fechaEmision' => ($propuesta->fecha_emision ?? now())->translatedFormat('d \d\e F \d\e Y'),
            'precioMensual' => $propuesta->precio_mensual,
            'horasMensuales' => $propuesta->horas_mensuales,
            'tarifaHora' => $propuesta->tarifa_hora,
            'subtotalHoras' => Calculos::subtotal($propuesta->horas_mensuales, $propuesta->tarifa_hora),
            'resumen' => $resumen,
            'situacion' => $situacion,
            'tablaComparacion' => $tablaComparacion,
            'contexto' => $contexto,
            'plan' => $plan,
            'condiciones' => $condiciones,
        ];
    }
}
